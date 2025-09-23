<?php

namespace Pack\Status\MessageHandler;

use Pack\Status\Entity\TestMessageLog;
use Pack\Status\Message\TestMessage;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Tests if messages in the queue are being consumed,
 * in a way that can be monitored automatically 
 * ie: we create or update TestMessageLogs - which can then be checked.
 * See MessengerCheckController::checkConsumer()
 */
#[AsMessageHandler()]
final class TestMessageHandler
{
    public function __construct(
        private EntityManager $entityManager,
        private LoggerInterface $messengerAuditLogger,
    )
    {
    }

    public function __invoke(TestMessage $message)
    {
        $testMessageLogs = $this->entityManager->getRepository(TestMessageLog::class)->findBy(
            criteria: [],
            orderBy: ['messageCreatedAt' => 'ASC'],
        );

        // if there already are 10 testMessageLogs, update oldest one
        // NB: we keep 10 testMessageLogs for good measure
        if (\count($testMessageLogs) >= 10) {

            // update oldest TestMessageLog
            $testMessageLog = $testMessageLogs[0];

            \assert($testMessageLog instanceof TestMessageLog);

            $testMessageLog->setMessageId($message->getId());
            $testMessageLog->setMessageCreatedAt($message->getCreatedAt());
            $testMessageLog->setMessageProcessedAt(new DateTimeImmutable());

            $this->entityManager->flush();

            return;
        }

        // if 10 testMessageLogs have not been created yet, add a new one

        $testMessageLog = new TestMessageLog();

        $testMessageLog->setMessageId($message->getId());
        $testMessageLog->setMessageCreatedAt($message->getCreatedAt());
        $testMessageLog->setMessageProcessedAt(new DateTimeImmutable());

        $this->entityManager->persist($testMessageLog);
        $this->entityManager->flush();
    }
}
