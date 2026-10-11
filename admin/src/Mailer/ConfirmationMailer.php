<?php

namespace Admin\Mailer;

use App\Entity\InterfacMatch;
use App\Entity\Player;
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

        $toConfirm = \count(array_filter($new, fn (array $item) => !$item['confirmed'])) + \count($changed) + \count($unconfirmed);
        $onlyCancellations = $new === [] && $changed === [] && $unconfirmed === [];

        $subject = match (true) {
            $onlyCancellations => 'Match Interfacs annulé',
            \count($changed) > 0 && \count($new) === 0 => 'Horaire Interfacs modifié',
            default => 'Matchs Interfacs programmés',
        };

        $email = (new TemplatedEmail())
            ->subject($subject)
            ->to($user->getEmail())
            ->replyTo('interfac@' . $this->getDomain())
            ->htmlTemplate('email/schedule_notification.html.twig')
            ->context([
                'name' => $user->getName(),
                'title' => $onlyCancellations ? 'Match annulé' : 'Tes matchs Interfacs',
                'new_matchs' => $new,
                'changed_matchs' => $changed,
                'cancelled_matchs' => $cancelled,
                'unconfirmed_matchs' => $unconfirmed,
                'to_confirm' => $toConfirm,
                'confirmation_link' => $toConfirm === 0 ? null : $this->confirmationLink($player),
                'confirmation_label' => $toConfirm > 1 ? 'Confirmer mes horaires' : 'Confirmer mon horaire',
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
     * Lien de connexion automatique vers "Mes matchs"
     */
    private function confirmationLink(Player $player): string
    {
        $loginLinkUrl = $this->loginLinkHandler->createLoginLink($player->getUser(), lifetime: self::LOGIN_LINK_LIFETIME)->getUrl();
        // goto must be relative path, not absolute url (see loginLinkSuccessHandler)
        $goto = $this->router->generate('my_matchs');
        $link = $loginLinkUrl . (str_contains($loginLinkUrl, '?') ? '&' : '?') . 'goto=' . rawurlencode($goto);

        return $this->fixLoginLinkHost($link);
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
