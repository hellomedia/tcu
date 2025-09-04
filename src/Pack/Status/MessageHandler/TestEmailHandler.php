<?php

namespace App\Pack\Status\MessageHandler;

use App\Pack\Status\Mailer\StatusMailer;
use App\Pack\Status\Message\TestEmail;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * See MessengerTestController.php
 */
#[AsMessageHandler()]
final class TestEmailHandler
{
    public function __construct(
        private StatusMailer $mailer,
        private EntityManager $entityManager,
    )
    {
    }

    public function __invoke(TestEmail $message)
    {
        $this->mailer->sendTestHtmlEmail(
            recipient: $message->getRecipient(),
            subject: $message->getSubject()
        );
    }
}
