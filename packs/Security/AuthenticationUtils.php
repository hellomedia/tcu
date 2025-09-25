<?php

namespace Pack\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils as BaseAuthenticationUtils;

/**
 * Authenticate user manually: UserAuthenticator::authenticateUser()
 * Other things: see base class.
 */
class AuthenticationUtils extends BaseAuthenticationUtils
{
    public function __construct(
        RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {
        parent::__construct($requestStack);
    }

    /**
     * Useful in cases where core user fields are changed.
     * ie: role switch.
     */
    public function updateToken(User $user, string $firewall): void
    {
        $token = new UsernamePasswordToken($user, $firewall, $user->getRoles());

        $this->tokenStorage->setToken($token);
    }
}
