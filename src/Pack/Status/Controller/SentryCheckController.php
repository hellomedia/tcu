<?php

namespace App\Pack\Status\Controller;

use App\Controller\BaseController;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Check Sentry by visiting /_check/sentry
 */
class SentryCheckController extends BaseController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route(name: "sentry_check", path: "/_check/sentry")]
    public function checkSentryLogging()
    {
        // test if monolog integration logs to sentry
        $this->logger->error('My test log error from visiting /_check/sentry');

        // test if an uncaught exception logs to sentry
        throw new \RuntimeException('My test exception from visiting /_check/sentry');
    }
}
