<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Date de dernière connexion (liste des invitations, utilisateurs)
 *
 * LoginSuccessEvent : formulaire (site et admin), lien de connexion (invitation, mot de passe oublié)
 * et remember me (retour avec une session mémorisée, une fois par session).
 * Pas déclenché par switch_user : se faire passer pour quelqu'un ne compte pas comme sa connexion.
 */
class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
