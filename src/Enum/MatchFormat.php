<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MatchFormat: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // Condition::cases() returns an array of cases, in order of declaration.
    case SINGLES = 'SIMPLE';
    case DOUBLES = 'DOUBLE';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->value;
    }
}