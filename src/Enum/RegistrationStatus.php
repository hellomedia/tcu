<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Statut de l'inscription d'un joueur pour une saison
 */
enum RegistrationStatus: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // inscription pré-remplie à partir d'une saison précédente, à vérifier par un admin
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    // inscription pré-remplie écartée : le joueur ne s'inscrit pas pour cette saison.
    // Conservée pour ne pas être recréée par le pré-remplissage.
    case DISMISSED = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'À confirmer',
            self::CONFIRMED => 'Confirmée',
            self::DISMISSED => 'Écartée',
        };
    }

    /**
     * Pour un filtre EasyAdmin : la valeur du filtre (et de l'url) est la valeur de l'enum
     *
     * @return array<string, string> label => value
     */
    public static function getFilterChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $status) {
            $choices[$status->getLabel()] = $status->value;
        }

        return $choices;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getLabel();
    }
}
