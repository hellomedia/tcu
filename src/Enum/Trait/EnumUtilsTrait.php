<?php

namespace App\Enum\Trait;

trait EnumUtilsTrait
{
    // Generates the choices array for EasyAdmin filters
    public static function getTranslatableChoices(): array
    {
        return array_combine(
            array_map(fn(self $enum) => $enum->value, self::cases()), // Keys: Enum values (used in the database)
            self::cases()  // Values: Enum (translatable)
        );
    }
}