<?php

namespace App\Security\Voter;

use App\Entity\InterfacMatch;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class MatchVoter extends Voter
{
    public const EDIT = 'EDIT';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::EDIT])) {
            return false;
        }

        // only vote on `InterfacMatch` objects
        if (!$subject instanceof InterfacMatch) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $user = $token->getUser();

        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        $match = $subject;

        assert($match instanceof InterfacMatch);

        return match ($attribute) {
            self::EDIT => $this->canEdit($match, $user),
            default => throw new \LogicException('This code should not be reached!')
        };
    }

    private function canEdit(InterfacMatch $match, User $user): bool
    {
        if ($match->isParticipant($user)) {
            return true;
        }

        return false;
    }
}
