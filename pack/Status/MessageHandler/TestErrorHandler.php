<?php

namespace Pack\Status\MessageHandler;

use Pack\Status\Message\TestError;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * See MessengerTestController.php
 */
#[AsMessageHandler()]
final class TestErrorHandler
{
    public function __construct(
    )
    {
    }

    public function __invoke(TestError $message)
    {
        throw new Exception('This is a test exception throw inside the queue');
    }
}
