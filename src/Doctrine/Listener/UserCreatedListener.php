<?php

namespace App\Doctrine\Listener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * https://symfony.com/doc/current/doctrine/events.html
 */
#[AsEntityListener(event: Events::prePersist, method: 'preCreate', entity: User::class, lazy: true)]
class UserCreatedListener
{
    public function preCreate(User $user, PrePersistEventArgs $args)
    {
        // unless already set in data fixtures
        if ($user->getCreatedAt() == null) {
            $user->setCreatedAt();
        }

        // unless already set in data fixtures
        if ($user->getUpdatedAt() == null) {
            $user->setUpdatedAt();
        }
    }
}
