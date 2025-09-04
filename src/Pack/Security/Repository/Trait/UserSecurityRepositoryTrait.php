<?php

namespace App\Pack\Security\Repository\Trait;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Implements PasswordUpgraderInterface
 * "If your user class is a Doctrine entity and you hash user passwords, the Doctrine repository class
 * related to the user class must implement the PasswordUpgraderInterface."
 * https://symfony.com/doc/current/security.html#registering-the-user-hashing-passwords
 */
trait UserSecurityRepositoryTrait
{
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     * Also exists in the user provider. Should it be centralized?
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Used in user provider, but also other places  (check status of user registration, ...)
     * User provider calls this. Should it be centralized in the user provider instead?
     */
    public function findUserByIdentifier(string $email): ?User
    {
        return $this->findOneBy([
            'email' => trim($email),
        ]);
    }
}
