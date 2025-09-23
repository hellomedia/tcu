<?php

namespace Pack\Status\Mailer;

use App\Mailer\BaseMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class StatusMailer extends BaseMailer
{
    public function sendTestEmail(string $recipient, string $subject)
    {
        $context = [
            'content' => $subject,
        ];

        $email = (new TemplatedEmail())
            ->subject($subject)
            ->to($recipient)
            ->replyTo('support@' . $this->getDomain())
            ->textTemplate('@status/email/test_email.txt.twig')
            ->context($context)
        ;

        $this->send($email);
    }

    public function sendTestHtmlEmail(string $recipient, string $subject)
    {
        $context = [
            'content' => $subject,
        ];

        $email = (new TemplatedEmail())
            ->subject($subject)
            ->to($recipient)
            ->replyTo('support@' . $this->getDomain())
            ->htmlTemplate('email/test.html.twig')
            ->context($context)
        ;

        $this->send($email);
    }
}
