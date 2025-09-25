<?php

namespace Pack\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * This Service is used at each request to automatically reload user data
 * from database, using user id in session, to make sure our user object
 * is up-to-date.
 */
class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private EntityManager $entityManager,
        private UserRepository $repository,
    ) {
    }

    public function loadUserByIdentifier($email): UserInterface
    {
        $user = $this->repository->findUserByIdentifier($email);

        if (!$user) {
            throw new UserNotFoundException(sprintf('No user with email "%s" was found.', $email));
        }

        return $user;
    }

    /**
     * Refresh a user = reload the user from id.
     *
     * Used to refresh the user object in session on each request
     * in case user was modified somewhere else
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException('Account is not supported.');
        }

        $userRefreshed = $this->repository->find($user->getId());

        if (!$userRefreshed) {
            throw new UserNotFoundException(sprintf('User with ID "%d" could not be reloaded.', $user->getId()));
        }

        return $userRefreshed;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException('Account is not supported.');
        }
        
        $user->setPassword($newHashedPassword);

        $this->entityManager->flush();
    }

    public function supportsClass($class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
