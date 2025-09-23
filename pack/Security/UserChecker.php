<?php

namespace Pack\Security;

use App\Entity\User;
use Pack\Security\Exception\EmailNotVerifiedException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User checker performs checks (disabled, locked...) pre-auth and post-auth
 * to see if user is allowed to login,
 * in addition to the cheks from the Authenticator, which only checks if :
  * - user exists
  * - login credentials are good
 * https://symfony.com/doc/current/security/user_checkers.html.
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * NB: scammers are hellbanned, not prevented from login in
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isAccountLocked()) {
            throw new LockedException();
        }

        if ($user->isAccountNonConfirmed()) {
            throw new EmailNotVerifiedException();
        }

        if ($user->isNotEnabled()) {
            throw new DisabledException();
        }

        if ($user->isAccountNotInService()) {
            // the message passed to this exception is meant to be displayed to the user
            throw new CustomUserMessageAccountStatusException('Account no longer valid.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // if ($user->isCredentialsExpired()) {
        //     throw new CredentialsExpiredException();
        // }
    }
}
