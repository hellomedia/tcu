<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\SeasonType;
use App\Repository\SeasonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Saison tennistique
 *
 * 2 saisons par an :
 * - Été YYYY (avril - octobre)
 * - Hiver YYYY-ZZZZ (novembre - mars)
 *
 * NB: les dates (App\Entity\Date) ne sont volontairement pas liées à une saison
 * car les saisons peuvent se chevaucher.
 * Ce sont les poules (et donc les matchs) et les inscriptions des joueurs
 * qui appartiennent à une saison.
 */
#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\UniqueConstraint(name: 'season_type_year_unique', columns: ['type', 'year'])]
#[UniqueEntity(fields: ['type', 'year'], message: 'Cette saison existe déjà.')]
class Season implements EntityInterface
{
    public const SLUG_REGEX = '(ete|hiver)-\d{4}(-\d{4})?';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: SeasonType::class)]
    private ?SeasonType $type = null;

    /**
     * Année de début de la saison
     * Hiver 2025-2026 => 2025
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $year = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startsOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endsOn = null;

    /**
     * Une seule saison courante à la fois : voir SeasonRepository::setCurrent()
     */
    #[ORM\Column(name: 'is_current', options: ['default' => false])]
    private bool $current = false;

    /**
     * Interfacs visibles sur le site public. Tant que la saison courante n'est pas publiée,
     * le site affiche une page d'attente (SeasonContext::getUnpublishedInterfacsSeason())
     */
    #[ORM\Column(name: 'is_published', options: ['default' => false])]
    private bool $published = false;

    public function __construct(?SeasonType $type = null, ?int $year = null)
    {
        $this->type = $type;
        $this->year = $year;

        $this->initDefaultDates();
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * "Été 2026" ou "Hiver 2025-2026"
     */
    public function getName(): string
    {
        if ($this->type === null || $this->year === null) {
            return '';
        }

        return match ($this->type) {
            SeasonType::SUMMER => sprintf('%s %d', $this->type->getLabel(), $this->year),
            SeasonType::WINTER => sprintf('%s %d-%d', $this->type->getLabel(), $this->year, $this->year + 1),
        };
    }

    /**
     * "ete-2026" ou "hiver-2025-2026"
     */
    public function getSlug(): string
    {
        if ($this->type === null || $this->year === null) {
            return '';
        }

        return match ($this->type) {
            SeasonType::SUMMER => sprintf('%s-%d', $this->type->value, $this->year),
            SeasonType::WINTER => sprintf('%s-%d-%d', $this->type->value, $this->year, $this->year + 1),
        };
    }

    /**
     * @return array{type: SeasonType, year: int}|null
     */
    public static function parseSlug(string $slug): ?array
    {
        if (!preg_match('/^(ete|hiver)-(\d{4})(?:-(\d{4}))?$/', $slug, $matches)) {
            return null;
        }

        $type = SeasonType::from($matches[1]);
        $year = (int) $matches[2];
        $endYear = isset($matches[3]) ? (int) $matches[3] : null;

        $isValid = match ($type) {
            SeasonType::SUMMER => $endYear === null,
            SeasonType::WINTER => $endYear === $year + 1,
        };

        return $isValid ? ['type' => $type, 'year' => $year] : null;
    }

    /**
     * Dates par défaut, modifiables
     * Été: 1er avril - 31 octobre
     * Hiver: 1er novembre - 31 mars
     */
    public function initDefaultDates(): static
    {
        if ($this->type === null || $this->year === null) {
            return $this;
        }

        if ($this->startsOn === null) {
            $this->startsOn = match ($this->type) {
                SeasonType::SUMMER => new \DateTimeImmutable(sprintf('%d-04-01', $this->year)),
                SeasonType::WINTER => new \DateTimeImmutable(sprintf('%d-11-01', $this->year)),
            };
        }

        if ($this->endsOn === null) {
            $this->endsOn = match ($this->type) {
                SeasonType::SUMMER => new \DateTimeImmutable(sprintf('%d-10-31', $this->year)),
                SeasonType::WINTER => new \DateTimeImmutable(sprintf('%d-03-31', $this->year + 1)),
            };
        }

        return $this;
    }

    public function getType(): ?SeasonType
    {
        return $this->type;
    }

    public function setType(SeasonType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isWinter(): bool
    {
        return $this->type === SeasonType::WINTER;
    }

    public function isSummer(): bool
    {
        return $this->type === SeasonType::SUMMER;
    }

    /**
     * Les interfacs se jouent en hiver
     */
    public function hasInterfacs(): bool
    {
        return $this->isWinter();
    }

    /**
     * Les interclubs se jouent en été
     */
    public function hasInterclubs(): bool
    {
        return $this->isSummer();
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getStartsOn(): ?\DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function setStartsOn(?\DateTimeImmutable $startsOn): static
    {
        $this->startsOn = $startsOn;

        return $this;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function setEndsOn(?\DateTimeImmutable $endsOn): static
    {
        $this->endsOn = $endsOn;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    /**
     * Ne pas utiliser directement : SeasonRepository::setCurrent()
     * garantit qu'il n'y a qu'une seule saison courante
     */
    public function setCurrent(bool $current): static
    {
        $this->current = $current;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }
}
