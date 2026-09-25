<?php

namespace Admin\Mailer;

use App\Entity\User;
use App\Mailer\BaseMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Invitation d'une personne dont un admin a créé le compte (PlayerAccountManager) :
 * lien de connexion qui mène à la page "Choisir mon mot de passe" (ChoosePasswordController).
 *
 * Envoyée à la demande d'un admin (Admin\Controller\InvitationController), pas à la création du compte.
 */
class InvitationMailer extends BaseMailer
{
    public const LIFETIME_DAYS = 14;

    public function sendInvitation(User $user): void
    {
        $loginLinkUrl = $this->loginLinkHandler->createLoginLink($user, lifetime: self::LIFETIME_DAYS * 24 * 60 * 60)->getUrl();
        // goto must be relative path, not absolute url (see LoginLinkSuccessHandler)
        $goto = $this->router->generate('password_choose');
        $invitationLink = $loginLinkUrl . (str_contains($loginLinkUrl, '?') ? '&' : '?') . 'goto=' . rawurlencode($goto);
        $invitationLink = $this->fixLoginLinkHost($invitationLink);

        $email = (new TemplatedEmail())
            ->subject('Votre compte Tennis Club Université')
            ->to($user->getEmail())
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'name' => $user->getPlayer()?->getFirstname() ?? $user->getName(),
                'account_email' => $user->getEmail(),
                'invitation_link' => $invitationLink,
                'lifetime_days' => self::LIFETIME_DAYS,
                'password_reset_link' => $this->generatePublicSiteAbsoluteUrl('password_reset_request'),
            ]);

        $this->send($email);

        $user->setInvitedAt(new \DateTimeImmutable());
    }
}
