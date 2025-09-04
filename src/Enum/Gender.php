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
    case MEN = 'M';
    case WOMEN = 'W';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::MEN  => $translator->trans('gender.men', domain: 'enum', locale: $locale),
            self::WOMEN => $translator->trans('gender.women', domain: 'enum', locale: $locale),
        };
    }
}