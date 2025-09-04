<?php

namespace App\Doctrine\Listener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * https://symfony.com/doc/current/doctrine/events.html
 */
#[AsEntityListener(event: Events::preUpdate, method: 'update', entity: User::class, lazy: true)]
class UserUpdatedListener
{
    public function update(User $user, PreUpdateEventArgs $args)
    {
        $user->setUpdatedAt();
    }
}
