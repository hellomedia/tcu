<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SeasonType: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    case SUMMER = 'ete';     // avril - octobre
    case WINTER = 'hiver';   // novembre - mars (à cheval sur 2 années)

    public function getLabel(): string
    {
        return match ($this) {
            self::SUMMER => 'Été',
            self::WINTER => 'Hiver',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getLabel();
    }
}
