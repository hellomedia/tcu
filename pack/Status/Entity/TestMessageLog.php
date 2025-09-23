<?php

namespace Pack\Status\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * TestMessageLog is a entity for logging when TestMessage messages are processed in the queue,
 * 
 * In Status\Controller\MessengerCheckController, pinged by uptime robot,
 *    we create a new TestMessage and send it to the queue.
 *    When TestMessage is processed in TestMessageHandler, the older TestMessageLog is updated and becomes the newest.
 *    Data of the latest TestMessageLog is then used to check if the queue is consumed normally.
 * 
 * NB: We only need the latest TestMessageLog. We keep 10 TestMessageLogs for good measure.
 */
#[ORM\Table(name: 'test_message_log')]
#[ORM\Entity]
class TestMessageLog
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $messageCreatedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $messageProcessedAt = null;

    public function getId()
    {
        return $this->id;
    }

    public function setMessageId(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageCreatedAt(DateTimeImmutable $messageCreatedAt): void
    {
        $this->messageCreatedAt = $messageCreatedAt;
    }

    public function getMessageCreatedAt(): ?DateTimeImmutable
    {
        return $this->messageCreatedAt;
    }

    public function setMessageProcessedAt(DateTimeImmutable $messageProcessedAt): void
    {
        $this->messageProcessedAt = $messageProcessedAt;
    }

    public function getMessageProcessedAt(): ?DateTimeImmutable
    {
        return $this->messageProcessedAt;
    }
}
