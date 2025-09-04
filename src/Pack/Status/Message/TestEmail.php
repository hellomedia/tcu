<?php

namespace App\Pack\Status\Message;

use App\Message\Interface\LowPriorityMessageInterface;

/**
 * See MessengerTestController.php
 */
final class TestEmail implements LowPriorityMessageInterface
{
    public function __construct(
        private string $recipient,
        private string $subject,
    )
    {
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}
