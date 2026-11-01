<?php

namespace Admin\Mailer;

use App\Entity\Group;
use App\Entity\InterfacMatch;
use App\Entity\Player;
use App\Entity\Practice;
use App\Entity\Season;
use App\Mailer\BaseMailer;
use App\Service\MatchNotifications;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Emails aux joueurs sur leurs matchs d'interfacs. Toujours déclenchés par l'admin, jamais automatiques.
 */
class ConfirmationMailer extends BaseMailer
{
    private const LOGIN_LINK_LIFETIME = 10 * 24 * 60 * 60; // 10 jours

    /**
     * Notification : tout ce que le joueur doit apprendre (nouveaux matchs, horaires modifiés, annulations)
     * + rappel des matchs déjà communiqués qu'il n'a pas encore confirmés.
     *
     * @return bool false si rien à envoyer (pas de compte, rien en attente)
     */
    public function sendScheduleNotification(Player $player, Season $season, MatchNotifications $notifications): bool
    {
        $user = $player->getUser();

        if ($user === null) {
            return false;
        }

        $collected = $notifications->collect($player, $season);

        if ($collected['pending'] === 0) {
            return false;
        }

        // a new match can already be confirmed: the player saw it on the site before this email
        $new = array_map(fn (array $item) => $this->describe($item['match'], $player, confirmed: (bool) $item['info']?->isConfirmed()), $collected['new']);
        $changed = array_map(fn (array $item) => $this->describe($item['match'], $player, $item['info']->getNotifiedLabel()), $collected['changed']);
        $cancelled = array_map(fn (array $item) => $this->describe($item['match'], $player, $item['info']->getNotifiedLabel()), $collected['cancelled']);
        $unconfirmed = array_map(fn (array $item) => $this->describe($item['match'], $player), $collected['unconfirmed']);

        // entraînements des poules du joueur : pas de confirmation, le joueur dit s'il vient (sur le site)
        $practices = $collected['practices'];
        $newPractices = array_map(fn (array $item) => $this->describePractice($item['practice'], $player), $practices['new']);
        $changedPractices = array_map(fn (array $item) => $this->describePractice($item['practice'], $player, $item['participant']->getNotifiedLabel()), $practices['changed']);
        $cancelledPractices = array_map(fn (array $item) => $this->describePractice($item['practice'], $player, $item['participant']->getNotifiedLabel()), $practices['cancelled']);
        $practicesToAnswer = \count(array_filter([...$newPractices, ...$changedPractices], fn (array $item) => $item['answer'] === null));

        $toConfirm = \count(array_filter($new, fn (array $item) => !$item['confirmed'])) + \count($changed) + \count($unconfirmed);
        $hasMatchs = $collected['pendingMatchs'] > 0 || $unconfirmed !== [];
        $onlyCancellations = $new === [] && $changed === [] && $unconfirmed === [] && $newPractices === [] && $changedPractices === [];

        $subject = match (true) {
            !$hasMatchs && $onlyCancellations => 'Entraînement Interfacs annulé',
            !$hasMatchs && $changedPractices !== [] && $newPractices === [] => 'Horaire d\'entraînement Interfacs modifié',
            !$hasMatchs => \count($newPractices) > 1 ? 'Entraînements Interfacs' : 'Entraînement Interfacs',
            $onlyCancellations => 'Match Interfacs annulé',
            \count($changed) > 0 && \count($new) === 0 => 'Horaire Interfacs modifié',
            default => 'Matchs Interfacs programmés',
        };

        // bouton vers le tableau de bord, où tout se confirme (matchs et entraînements)
        if ($toConfirm > 0) {
            $link = $this->confirmationLink($player);
            $label = $toConfirm > 1 ? 'Confirmer mes horaires' : 'Confirmer mon horaire';
        } elseif ($practicesToAnswer > 0) {
            $link = $this->confirmationLink($player);
            $label = 'Dire si je viens';
        } else {
            $link = null;
            $label = null;
        }

        $email = (new TemplatedEmail())
            ->subject($subject)
            ->to($user->getEmail())
            ->replyTo('interfac@' . $this->getDomain())
            ->htmlTemplate('email/schedule_notification.html.twig')
            ->context([
                'name' => $user->getName(),
                'title' => match (true) {
                    !$hasMatchs && $onlyCancellations => 'Entraînement annulé',
                    !$hasMatchs => 'Tes entraînements Interfacs',
                    $onlyCancellations => 'Match annulé',
                    default => 'Tes matchs Interfacs',
                },
                'new_matchs' => $new,
                'changed_matchs' => $changed,
                'cancelled_matchs' => $cancelled,
                'unconfirmed_matchs' => $unconfirmed,
                'to_confirm' => $toConfirm,
                'has_matchs' => $hasMatchs,
                'new_practices' => $newPractices,
                'changed_practices' => $changedPractices,
                'cancelled_practices' => $cancelledPractices,
                'practices_to_answer' => $practicesToAnswer,
                'confirmation_link' => $link,
                'confirmation_label' => $label,
            ]);

        $this->send($email);

        return true;
    }

    /**
     * Rappel des matchs communiqués mais pas encore confirmés
     *
     * @return bool false si rien à rappeler, ou si une notification est en attente (à envoyer d'abord)
     */
    public function sendReminder(Player $player, Season $season, MatchNotifications $notifications): bool
    {
        $user = $player->getUser();

        if ($user === null) {
            return false;
        }

        $collected = $notifications->collect($player, $season);

        if ($collected['pending'] > 0 || $collected['unconfirmed'] === []) {
            return false;
        }

        $unconfirmed = array_map(fn (array $item) => $this->describe($item['match'], $player), $collected['unconfirmed']);

        $email = (new TemplatedEmail())
            ->subject('Rappel : confirme ' . (\count($unconfirmed) > 1 ? 'tes horaires' : 'ton horaire') . ' Interfacs')
            ->to($user->getEmail())
            ->replyTo('interfac@' . $this->getDomain())
            ->htmlTemplate('email/schedule_reminder.html.twig')
            ->context([
                'name' => $user->getName(),
                'title' => 'Petit rappel',
                'unconfirmed_matchs' => $unconfirmed,
                'confirmation_link' => $this->confirmationLink($player),
                'confirmation_label' => \count($unconfirmed) > 1 ? 'Confirmer mes horaires' : 'Confirmer mon horaire',
            ]);

        $this->send($email);

        return true;
    }

    /**
     * Lien de connexion automatique vers le tableau de bord de Mon TCU : tout ce qui est à venir
     * s'y confirme (horaires des matchs, présence aux entraînements)
     */
    private function confirmationLink(Player $player): string
    {
        $loginLinkUrl = $this->loginLinkHandler->createLoginLink($player->getUser(), lifetime: self::LOGIN_LINK_LIFETIME)->getUrl();
        // goto must be relative path, not absolute url (see loginLinkSuccessHandler)
        $goto = $this->router->generate('dashboard');
        $link = $loginLinkUrl . (str_contains($loginLinkUrl, '?') ? '&' : '?') . 'goto=' . rawurlencode($goto);

        return $this->fixLoginLinkHost($link);
    }

    /**
     * Données sérialisables d'un entraînement pour le template
     *
     * @return array{date: ?string, time: ?string, court: ?string, label: string, titled: bool, note: ?string, previous: ?string, answer: ?bool, coming: int}
     */
    private function describePractice(Practice $practice, Player $player, ?string $previous = null): array
    {
        return [
            'date' => $practice->getDayLabel(),
            'time' => $practice->getTimeRange(),
            'court' => $practice->getCourtsLabel(),
            'label' => $practice->getLabel(),
            'titled' => $practice->getTitle() !== null, // sinon le label est le nom des poules
            // invités : les poules (sauf quand elles sont le label) et les joueurs liés un par un (sauf le destinataire)
            'with' => implode(', ', [
                ...($practice->getTitle() !== null ? $practice->getGroups()->map(fn (Group $group) => (string) $group)->toArray() : []),
                ...$practice->getIndividualPlayers()->filter(fn (Player $invited) => $invited !== $player)->map(fn (Player $invited) => $invited->getName())->toArray(),
            ]) ?: null,
            'note' => $practice->getNote(),
            'previous' => $previous,
            'answer' => $practice->getAnswerOf($player), // déjà répondu sur le site
            'coming' => \count($practice->getComingPlayers()),
        ];
    }

    /**
     * Données sérialisables d'un match pour le template (l'email est pré-rendu, voir BaseMailer::send)
     *
     * @return array{slot: ?string, date: ?string, time: ?string, court: ?string, opponent: string, partner: string, previous: ?string, confirmed: bool}
     */
    private function describe(InterfacMatch $match, Player $player, ?string $previous = null, bool $confirmed = false): array
    {
        $slot = $match->getSlot();
        $names = fn (array $players) => implode(' et ', array_map(fn (Player $other) => $other->getName(), $players));

        return [
            'slot' => $slot ? (string) $slot : null,
            'date' => $slot ? (string) $slot->getDate() : null,
            'time' => $slot?->getTimeRange(),
            'court' => $slot ? (string) $slot->getCourt() : null,
            'opponent' => $names($match->getOpponentsOf($player)),
            'partner' => $names($match->getPartnersOf($player)), // doubles, empty in singles
            'previous' => $previous,
            'confirmed' => $confirmed,
        ];
    }
}
