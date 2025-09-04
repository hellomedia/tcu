<?php

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// https://symfony.com/doc/current/security/voters.html
final class MessageVoter extends Voter
{
    public const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // only vote on `Post` objects
        if (!$subject instanceof Message) {
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

        $message = $subject;

        \assert($message instanceof Message);

        switch ($attribute) {
            case self::VIEW:
                return $this->_canView($message, $user);
                break;
        }

        return false;
    }

    private function _canView(Message $message, User $user): bool
    {
        return $message->getConversation()->hasParticipant($user);
    }
}
