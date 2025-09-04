<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// https://symfony.com/doc/current/security/voters.html
final class ConversationVoter extends Voter
{
    public const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // only vote on `Post` objects
        if (!$subject instanceof Conversation) {
            return false;
        }

        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::VIEW])) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        $conversation = $subject;

        \assert($conversation instanceof Conversation);

        switch ($attribute) {
            case self::VIEW:
                return $this->_canView($conversation, $user);
                break;
        }

        return false;
    }

    private function _canView(Conversation $conversation, User $user): bool
    {
        return $conversation->hasParticipant($user);
    }
}
