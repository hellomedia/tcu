<?php

namespace App\Validator;

use App\Entity\Player;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class AvailableAccountEmailValidator extends ConstraintValidator
{
    public function __construct(
        private UserRepository $userRepository,
    )
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableAccountEmail) {
            throw new UnexpectedTypeException($constraint, AvailableAccountEmail::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $player = $this->context->getObject();

        if (!$player instanceof Player) {
            throw new UnexpectedTypeException($player, Player::class);
        }

        $user = $this->userRepository->findOneBy(['email' => $value]);

        // pas de compte avec cet email, ou c'est déjà celui du joueur : PlayerAccountManager::sync() fait le reste
        if ($user === null || $user === $player->getUser()) {
            return;
        }

        if ($user->getPlayer() !== null) {
            $this->context->buildViolation($constraint->otherPlayerMessage)
                ->setParameter('{{ player }}', $user->getPlayer()->getName())
                ->addViolation();
        } elseif ($player->hasAccount()) {
            $this->context->buildViolation($constraint->otherAccountMessage)
                ->setParameter('{{ email }}', $player->getUser()->getEmail())
                ->addViolation();
        }
    }
}
