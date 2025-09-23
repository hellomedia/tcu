<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum AccountLanguage: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // OfferType::cases() returns an array of cases, in order of declaration.

    case FRENCH = 'fr';
    case ENGLISH = 'en';
    case NEDERLANDS = 'nl';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::FRENCH  => $translator->trans('locale.fr', locale: $locale),
            self::ENGLISH => $translator->trans('locale.en', locale: $locale),
            self::NEDERLANDS => $translator->trans('locale.nl', locale: $locale),
        };
    }

}