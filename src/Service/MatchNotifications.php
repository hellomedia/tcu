<?php

namespace App\Service;

use App\Entity\InterfacMatch;
use App\Entity\MatchParticipant;
use App\Entity\ParticipantConfirmationInfo;
use App\Entity\Player;
use App\Entity\Season;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ce qu'un joueur doit encore apprendre par email sur ses matchs d'interfacs de la saison,
 * à partir de ParticipantConfirmationInfo (créneau communiqué lors du dernier email).
 *
 * Les emails ne partent jamais automatiquement : l'admin décide quand notifier (page Notifications,
 * modale de confirmation d'un match). Un email reprend tout ce qui est en attente pour le joueur :
 * nouveaux matchs, horaires modifiés, matchs annulés, plus les matchs déjà communiqués à confirmer.
 */
class MatchNotifications
{
    public function __construct(
        private GroupRepository $groupRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Joueurs des poules de la saison, triés par nom
     *
     * @return Player[]
     */
    public function getPlayers(Season $season): array
    {
        $players = [];

        foreach ($this->groupRepository->findBySeason($season) as $group) {
            foreach ($group->getPlayers() as $player) {
                $players[$player->getId()] = $player;
            }
        }

        usort($players, fn (Player $a, Player $b) => [$a->getLastname(), $a->getFirstname()] <=> [$b->getLastname(), $b->getFirstname()]);

        return $players;
    }

    /**
     * État des notifications d'un joueur pour la saison
     *
     * @return array{
     *     new: list<array{match: InterfacMatch, participant: MatchParticipant, info: ?ParticipantConfirmationInfo}>,
     *     changed: list<array{match: InterfacMatch, participant: MatchParticipant, info: ParticipantConfirmationInfo}>,
     *     cancelled: list<array{match: InterfacMatch, participant: MatchParticipant, info: ParticipantConfirmationInfo}>,
     *     unconfirmed: list<array{match: InterfacMatch, participant: MatchParticipant, info: ParticipantConfirmationInfo}>,
     *     pending: int,
     *     lastEmailAt: ?\DateTimeImmutable,
     *     remindedAt: ?\DateTimeImmutable
     * }
     */
    public function collect(Player $player, Season $season): array
    {
        $result = ['new' => [], 'changed' => [], 'cancelled' => [], 'unconfirmed' => [], 'pending' => 0, 'lastEmailAt' => null, 'remindedAt' => null];

        foreach ($player->getMatchs($season) as $match) {
            $participant = $this->participantOf($match, $player);
            if ($participant === null) {
                continue;
            }
            $info = $participant->getConfirmationInfo();
            $item = ['match' => $match, 'participant' => $participant, 'info' => $info];

            if ($info === null) {
                if ($match->isUpcoming()) {
                    $result['new'][] = $item;
                }
                continue;
            }

            if ($info->getEmailSentAt() && $info->getEmailSentAt() > $result['lastEmailAt']) {
                $result['lastEmailAt'] = $info->getEmailSentAt();
            }
            if ($info->getRemindedAt() && $info->getRemindedAt() > $result['remindedAt']) {
                $result['remindedAt'] = $info->getRemindedAt();
            }

            switch ($info->getPendingNotification()) {
                case ParticipantConfirmationInfo::PENDING_NEW:
                    $result['new'][] = $item;
                    break;
                case ParticipantConfirmationInfo::PENDING_CHANGED:
                    $result['changed'][] = $item;
                    break;
                case ParticipantConfirmationInfo::PENDING_CANCELLED:
                    $result['cancelled'][] = $item;
                    break;
                default:
                    if ($match->isUpcoming() && $info->isEmailSent() && !$info->isConfirmed()) {
                        $result['unconfirmed'][] = $item;
                    }
            }
        }

        $result['pending'] = \count($result['new']) + \count($result['changed']) + \count($result['cancelled']);

        return $result;
    }

    /**
     * Après l'envoi de l'email : chaque match en attente est désormais communiqué avec son créneau actuel
     * (ou son annulation). Les infos manquantes sont créées.
     *
     * @param array<string, mixed> $collected voir collect()
     *
     * @return MatchParticipant[] participants dont l'état a changé (pastilles à rafraîchir)
     */
    public function markNotified(array $collected, \DateTimeImmutable $now): array
    {
        $participants = [];

        foreach (['new', 'changed', 'cancelled'] as $kind) {
            foreach ($collected[$kind] as $item) {
                $participant = $item['participant'];
                $info = $item['info'];

                if ($info === null) {
                    $info = (new ParticipantConfirmationInfo())->setParticipant($participant);
                    $this->entityManager->persist($info);
                    $participant->setConfirmationInfo($info);
                }

                $info->setIsEmailSent(true);
                $info->setEmailSentAt($now);
                $info->setRemindedAt(null);
                $info->snapshotSlot($item['match']->getSlot()); // null when cancelled

                $participants[] = $participant;
            }
        }

        // the matchs still to confirm were listed again in the email : counts as a reminder
        foreach ($collected['unconfirmed'] as $item) {
            $item['info']->setRemindedAt($now);
        }

        return $participants;
    }

    /**
     * Après l'envoi d'un rappel
     *
     * @param array<string, mixed> $collected voir collect()
     *
     * @return MatchParticipant[]
     */
    public function markReminded(array $collected, \DateTimeImmutable $now): array
    {
        $participants = [];

        foreach ($collected['unconfirmed'] as $item) {
            $item['info']->setRemindedAt($now);
            $participants[] = $item['participant'];
        }

        return $participants;
    }

    /**
     * Vue d'ensemble pour la page Notifications
     *
     * @return list<array{player: Player, collected: array<string, mixed>}>
     */
    public function overview(Season $season): array
    {
        $rows = [];

        foreach ($this->getPlayers($season) as $player) {
            $rows[] = ['player' => $player, 'collected' => $this->collect($player, $season)];
        }

        return $rows;
    }

    private function participantOf(InterfacMatch $match, Player $player): ?MatchParticipant
    {
        foreach ($match->getParticipants() as $participant) {
            if ($participant->getPlayer() === $player) {
                return $participant;
            }
        }

        return null;
    }
}
