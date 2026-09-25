<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Sur Player::$accountEmail : l'email ne doit pas appartenir au compte d'un autre joueur
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AvailableAccountEmail extends Constraint
{
    public string $otherPlayerMessage = 'Cet email est celui du compte d\'un autre joueur ({{ player }}).';
    public string $otherAccountMessage = 'Ce joueur a déjà un compte ({{ email }}) : cet email appartient à un autre compte.';
}
