<?php

namespace Admin\Mailer;

use App\Entity\MatchParticipant;
use App\Mailer\BaseMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class ConfirmationMailer extends BaseMailer
{
    public function sendScheduleNotification(MatchParticipant $participant): bool
    {
        $user = $participant->getUser();

        if (!$user) {
            return false;
        }

        $tenDaysinSeconds = 10 * 24 * 60 * 60;
        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($participant->getUser(), lifetime: $tenDaysinSeconds);
        $loginLinkUrl = $loginLinkDetails->getUrl();
        // goto must be relative path, not absolute url (see loginLinkSuccessHandler)
        $goto = $this->router->generate('my_matchs');
        $confirmationLink = $loginLinkUrl . (str_contains($loginLinkUrl, '?') ? '&' : '?') . 'goto=' . rawurlencode($goto);
        $confirmationLink = $this->fixLoginLinkHost($confirmationLink);

        $player = $participant->getPlayer();
        $unconfirmedScheduledMatchs = $player->getUnconfirmedScheduledMatchs();

        if ($unconfirmedScheduledMatchs->count() == 0) {
            return false;
        }

        $subject = match ($unconfirmedScheduledMatchs->count()) {
            1 => 'Match Interfacs programmé',
            default => 'Matchs Interfacs programmés',
        };
        $title = match ($unconfirmedScheduledMatchs->count()) {
            1 => 'Match programmé',
            default => 'Matchs programmés',
        };
        $confirmationLabel = match ($unconfirmedScheduledMatchs->count()) {
            1 => 'Confirmer mon horaire',
            default => 'Confirmer mes horaires',
        };

        $email = (new TemplatedEmail())
            ->subject($subject)
            ->to($user->getEmail())
            ->replyTo('interfac@' . $this->getDomain())
            ->htmlTemplate('email/schedule_notification.html.twig')
            ->context([
                'name' => $user->getName(),
                'title' => $title,
                'unconfirmed_matchs' => $unconfirmedScheduledMatchs,
                'confirmation_link' => $confirmationLink,
                'confirmation_label' => $confirmationLabel,
            ]);

        $this->send($email);

        return true;
    }
}
