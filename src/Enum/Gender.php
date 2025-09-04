<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Gender: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // Condition::cases() returns an array of cases, in order of declaration.
    case MEN = 'Homme';
    case WOMEN = 'Femme';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->value;
    }
}