<?php

namespace Pack\Status\Message;

use App\Message\Interface\LowPriorityMessageInterface;
use DateTimeImmutable;

/**
 * See TestMessageHandler and MessengerCheckController::checkConsumer()
 */
final class TestMessage implements LowPriorityMessageInterface
{
    private DateTimeImmutable $createdAt;
    private string $id;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();

        $this->id = 'test message ' . $this->createdAt->format('Y-m-d H:i:s:v');
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
