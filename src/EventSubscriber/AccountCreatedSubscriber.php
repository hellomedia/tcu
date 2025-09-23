<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Event\AccountCreatedEvent;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManager $entityManager,
        private LoggerInterface $logger,
        private string $environment,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AccountCreatedEvent::NAME => [
                ['performChecks', 10],
                ['hashPassword', 9]
            ]
        ];
    }

    public function performChecks(AccountCreatedEvent $event): void
    {
        // call autochecker if necessary
    }

    public function hashPassword(AccountCreatedEvent $event): void
    {
        $this->_hashPassword(
            user: $event->getUser(),
            plainPassword: $event->getPassword(),
        );
    }

    private function _hashPassword(User $user, string $plainPassword): void
    {
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                user: $user,
                plainPassword: $plainPassword,
            )
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
