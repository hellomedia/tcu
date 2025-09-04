<?php

namespace App\Service;

use App\Entity\User;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * NB: sentry client is Sentry\Client.
 * This is a helper class for defining beforeSend listener
 * https://docs.sentry.io/platforms/php/guides/symfony/data-management/sensitive-data/#scrubbing-data
 */
class Sentry
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private HubInterface $hub,
    ) {}

    /**
     * This seems to be linked to the send_default_pii: true flag in sentry.yaml
     * If flag is false, nothing is sent.
     * If flag is true, it seems like we still need this.
     * I don't get it, but it seems to work with both the flag and this.
     * 
     * If user exists, send the user id for sentry.
     * Else, send the ip, which will be used as user identifier.
     * 
     * IP should be useful for linking sentry errors belonging to a bot,
     * which would not have an account (thus no ip in admin) and no js tracker data.
     */
    public function getBeforeSend(): callable
    {
        return function (Event $event): ?Event {

            $request = $this->requestStack->getCurrentRequest();

            // If there's no request (e.g., in a worker, in the queue), skip filtering
            if (!$request) {
                return $event;
            }

            $exceptions = $event->getExceptions();

            foreach ($exceptions as $exceptionDataBag) {

                \assert($exceptionDataBag instanceof ExceptionDataBag);

                $exceptionType = $exceptionDataBag->getType();

                // Ignore MethodNotAllowedHttpException for specific endpoints
                if ($exceptionType === MethodNotAllowedHttpException::class) {

                    $path = $request->getPathInfo();

                    // List of URL endings to ignore
                    $ignoredEnding = [
                        '/teaser-view/new',
                        '/phone-number-view/new',
                    ];

                    foreach ($ignoredEnding as $ending) {
                        if (str_ends_with($path, $ending)) {
                            return null; // Drop the event
                        }
                    }
                }
            }

            $user = $this->security->getUser();

            $userData = new UserDataBag();

            if ($user instanceof User) {
                $userData->setId($user->getId());
            } else {
                $userData->setIpAddress($this->requestStack->getCurrentRequest()->getClientIp());
            }

            $this->hub->configureScope(function (Scope $scope) use ($userData): void {
                $scope->setUser($userData);
            });

            return $event;
        };
    }
}
