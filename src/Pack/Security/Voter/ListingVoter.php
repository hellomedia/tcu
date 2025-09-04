<?php

namespace App\Pack\Security\Voter;

use App\Entity\Listing;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class ListingVoter extends Voter
{
    public const MANAGE = 'MANAGE';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::MANAGE])) {
            return false;
        }

        if (!$subject instanceof Listing) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Admin can manage
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $user = $token->getUser();

        $listing = $subject;
        \assert($listing instanceof Listing);

        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::MANAGE:
                return $this->_canManageIfNotAdmin($user, $listing);
                break;
        }

        return false;
    }

    private function _canManageIfNotAdmin(User $user, Listing $listing): bool
    {
        return $user == $listing->getPoster();
    }
}
