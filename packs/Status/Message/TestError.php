<?php

namespace Pack\Status\Message;

use App\Message\Interface\LowPriorityMessageInterface;

/**
 * See MessengerTestController.php
 */
final class TestError implements LowPriorityMessageInterface
{
    public function __construct(
    )
    {
    }
}
