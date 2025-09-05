<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SlotDuration: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // Condition::cases() returns an array of cases, in order of declaration.
    case THIRTY_MINUTE = '30 minutes';
    case ONE_HOUR = '1 heure';
    case ONE_HOUR_HALF = '1 heure 30';
    case TWO_HOURS = '2 heures';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->value;
    }


    public function toInterval(): \DateInterval
    {
        return match ($this) {
            self::THIRTY_MINUTE  => new \DateInterval('PT30M'),
            self::ONE_HOUR       => new \DateInterval('PT1H'),
            self::ONE_HOUR_HALF  => new \DateInterval('PT1H30M'),
            self::TWO_HOURS      => new \DateInterval('PT2H'),
        };
    }

    public function minutes(): int
    {
        return match ($this) {
            self::THIRTY_MINUTE  => 30,
            self::ONE_HOUR       => 60,
            self::ONE_HOUR_HALF  => 90,
            self::TWO_HOURS      => 120,
        };
    }
}