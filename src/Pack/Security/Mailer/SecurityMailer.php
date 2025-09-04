<?php

namespace App\Pack\Security\Mailer;

use App\Entity\User;
use App\Mailer\BaseMailer;
use App\Pack\Security\EmailVerifier;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Service\Attribute\Required;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class SecurityMailer extends BaseMailer
{
    private EmailVerifier $emailVerifier;

    #[Required]
    public function setEmailVerifier(EmailVerifier $emailVerifier): void
    {
        $this->emailVerifier = $emailVerifier;
    }

    public function sendRegistrationValidationLink(User $user)
    {
        $email = (new TemplatedEmail())
            ->subject($this->translator->trans('registration.validation_email.subject', [], 'security'))
            ->to($user->getEmail())
            ->replyTo('support@' . $this->getDomain())
            ->textTemplate('@security/email/registration_validation.txt.twig')
            ->htmlTemplate('@security/email/registration_validation.html.twig')
            ->context([
                'name' => $user->getName(),
                'signedUrl' => $this->emailVerifier->getSignedUrl($user),
            ]);

        $this->send($email);
    }

    public function sendResetPasswordEmail(User $user, ResetPasswordToken $resetToken)
    {
        $email = (new TemplatedEmail())
            ->subject($this->translator->trans('password_reset.email.subject', [], 'security'))
            ->to($user->getEmail())
            ->replyTo('support@' . $this->getDomain())
            ->textTemplate('@security/email/reset_password.txt.twig')
            ->htmlTemplate('@security/email/reset_password.html.twig')
            ->context([
                'name' => $user->getName(),
                'resetToken' => $resetToken,
            ]);

        $this->send($email);
    }
}
