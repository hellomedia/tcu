<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * D'où vient le classement d'une inscription
 */
enum RankingSource: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // encodé à la main dans l'admin
    case MANUAL = 'manual';
    // repris d'une saison précédente lors du pré-remplissage : à mettre à jour
    case PREVIOUS_SEASON = 'previous';
    // classement prévisionnel récupéré sur mon-classement-tennis.be : à remplacer par le classement officiel
    case PREVISIONAL = 'previsional';
    // classement officiel récupéré sur mon-classement-tennis.be
    case OFFICIAL = 'official';

    public function getLabel(): string
    {
        return match ($this) {
            self::MANUAL => 'Manuel',
            self::PREVIOUS_SEASON => 'Saison précédente',
            self::PREVISIONAL => 'Prévisionnel',
            self::OFFICIAL => 'Officiel',
        };
    }

    /**
     * @return array<string, string> label => value (filtre EasyAdmin)
     */
    public static function getFilterChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $source) {
            $choices[$source->getLabel()] = $source->value;
        }

        return $choices;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getLabel();
    }
}
