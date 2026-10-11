<?php

namespace Admin\Mailer;

use App\Entity\Season;
use App\Entity\User;
use App\Mailer\BaseMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * Invitation d'une personne dont un admin a créé le compte (PlayerAccountManager) :
 * lien de connexion qui mène à la page "Choisir mon mot de passe" (ChoosePasswordController).
 *
 * Envoyée à la demande d'un admin (Admin\Controller\InvitationController), pas à la création du compte.
 *
 * Deux versions : "Ton compte Interfacs" (tutoiement) pour un joueur inscrit aux interfacs de la saison donnée,
 * "Votre compte Tennis Club Université" pour les autres comptes.
 */
class InvitationMailer extends BaseMailer
{
    public const LIFETIME_DAYS = 14;

    private const INTERFACS_SUBJECT = 'Ton compte Interfacs (TC Université)';
    private const INTERFACS_SENDER_NAME = 'TC Université';

    /**
     * @param Season|null $season saison sélectionnée dans l'admin : version interfacs si le joueur y est inscrit aux interfacs
     */
    public function sendInvitation(User $user, ?Season $season = null): void
    {
        $loginLinkUrl = $this->loginLinkHandler->createLoginLink($user, lifetime: self::LIFETIME_DAYS * 24 * 60 * 60)->getUrl();
        // goto must be relative path, not absolute url (see LoginLinkSuccessHandler)
        $goto = $this->router->generate('password_choose');
        $invitationLink = $loginLinkUrl . (str_contains($loginLinkUrl, '?') ? '&' : '?') . 'goto=' . rawurlencode($goto);
        $invitationLink = $this->fixLoginLinkHost($invitationLink);

        // getRegistration() ignore les inscriptions écartées
        $interfacs = $user->getPlayer()?->getRegistration($season)?->isInterfacs() ?? false;

        $email = (new TemplatedEmail())
            ->subject($interfacs ? self::INTERFACS_SUBJECT : 'Votre compte Tennis Club Université')
            ->to($user->getEmail())
            ->htmlTemplate($interfacs ? 'email/invitation_interfacs.html.twig' : 'email/invitation.html.twig')
            ->context([
                'name' => $user->getPlayer()?->getFirstname() ?? $user->getName(),
                'account_email' => $user->getEmail(),
                'invitation_link' => $invitationLink,
                'lifetime_days' => self::LIFETIME_DAYS,
                'password_reset_link' => $this->generatePublicSiteAbsoluteUrl('password_reset_request'),
                'title' => self::INTERFACS_SUBJECT,
                'season_name' => (string) $season,
            ]);

        $this->send($email, fromName: $interfacs ? self::INTERFACS_SENDER_NAME : null);

        $user->setInvitedAt(new \DateTimeImmutable());
    }
}
