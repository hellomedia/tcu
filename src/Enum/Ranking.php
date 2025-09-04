<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Ranking: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // Condition::cases() returns an array of cases, in order of declaration.
    case NC = 'NC';
    case C_30_6 = 'C30.6';
    case C_30_5 = 'C30.5';
    case C_30_4 = 'C30.4';
    case C_30_3 = 'C30.3';
    case C_30_2 = 'C30.2';
    case C_30_1 = 'C30.1';
    case C_30   = 'C30';
    case C_15_5 = 'C15.5';
    case C_15_4 = 'C15.4';
    case C_15_2 = 'C15.3';
    case C_15_3 = 'C15.2';
    case C_15_1 = 'C15.1';
    case C_15   = 'C15';
    case B_4    = 'B+4/6';
    case B_2    = 'B+2/6';
    case B_0    = 'B0';
    case B_M2   = 'B-2/6';
    case B_M4   = 'B-4/6';
    case B_M15  = 'B-15';
    case B_M15_1   = 'B-15/1';
    case B_M15_2   = 'B-15/2';
    case B_M15_4   = 'B-15/4';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->value;
    }

    public function getPoints(): int
    {
        return match ($this) {
            self::NC        => 1,
            self::C_30_6    => 3,
            self::C_30_5    => 5,
            self::C_30_4    => 10,
            self::C_30_3    => 15,
            self::C_30_2    => 20,
            self::C_30_1    => 25,
            self::C_30      => 30,
            self::C_15_5    => 35,
            self::C_15_4    => 40,
            self::C_15_3    => 45,
            self::C_15_2    => 50,
            self::C_15_1    => 55,
            self::C_15      => 60,
            self::B_4       => 65,
            self::B_2       => 70,
            self::B_0       => 75,
            self::B_M2      => 80,
            self::B_M4      => 85,
            self::B_M15     => 90,
            self::B_M15_1   => 95,
            self::B_M15_2   => 100,
            self::B_M15_4   => 105
        };
    }
}