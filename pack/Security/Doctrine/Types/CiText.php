<?php

namespace Pack\Security\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

/**
 * NB: Activate extension in database by adding this in migration:
 *     CREATE EXTENSION IF NOT EXISTS citext;
 */
final class CiText extends TextType
{
    const NAME = 'citext'; // lowercase for postgresql

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getDoctrineTypeMapping(self::NAME);
    }
}
