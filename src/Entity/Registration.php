<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\Ranking;
use App\Enum\RankingSource;
use App\Enum\RegistrationStatus;
use App\Repository\RegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Inscription d'un joueur pour une saison
 *
 * Tout ce qui change d'une saison à l'autre :
 * classement (2x par an) et activités (interfacs, interclubs, cours)
 *
 * Interfacs : uniquement en hiver. Interclubs : uniquement en été.
 * cf Season::hasInterfacs() et Season::hasInterclubs()
 */
#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'registration_unique', columns: ['player_id', 'season_id'])]
#[UniqueEntity(fields: ['player', 'season'], message: 'Ce joueur est déjà inscrit pour cette saison.')]
class Registration implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // cascade persist : le formulaire "Nouvelle inscription" peut créer le joueur en même temps
    #[ORM\ManyToOne(inversedBy: 'registrations', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    #[ORM\Column(nullable: true, enumType: Ranking::class)]
    private ?Ranking $ranking = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rankingOrder = null; // <-- for sorting

    /**
     * D'où vient le classement : encodé à la main, repris d'une saison précédente,
     * ou récupéré sur mon-classement-tennis.be (prévisionnel ou officiel)
     */
    #[ORM\Column(nullable: true, enumType: RankingSource::class)]
    private ?RankingSource $rankingSource = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rankingFetchedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $interfacs = null;

    #[ORM\Column(nullable: true)]
    private ?bool $interclubs = null;

    #[ORM\Column(nullable: true)]
    private ?bool $cours = null;

    /**
     * Disponibilités pour les matchs de la saison, en texte libre
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $availabilities = null;

    #[ORM\Column(enumType: RegistrationStatus::class, options: ['default' => RegistrationStatus::CONFIRMED->value])]
    private RegistrationStatus $status = RegistrationStatus::CONFIRMED;

    public function __toString(): string
    {
        return $this->player . ' - ' . $this->season;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getRanking(): ?Ranking
    {
        return $this->ranking;
    }

    /**
     * Classement encodé à la main (formulaire de l'admin).
     * Pour un classement récupéré ou repris d'une saison précédente : setRankingFrom()
     */
    public function setRanking(?Ranking $ranking): static
    {
        // le formulaire renvoie aussi le classement quand il n'a pas changé : on garde alors sa source
        if ($ranking !== $this->ranking) {
            $this->rankingSource = $ranking === null ? null : RankingSource::MANUAL;
            $this->rankingFetchedAt = null;
        }

        $this->ranking = $ranking;

        // Update the sorting order
        if ($ranking === null) {
            $this->rankingOrder = null;
        } else {
            // Get the array of all enum cases in their declared order
            $order = array_flip(array_column(Ranking::cases(), 'name'));
            $this->rankingOrder = $order[$ranking->name] ?? null;
        }

        return $this;
    }

    public function getRankingOrder(): ?int
    {
        return $this->rankingOrder;
    }

    public function setRankingFrom(?Ranking $ranking, RankingSource $source, ?\DateTimeImmutable $fetchedAt = null): static
    {
        $this->setRanking($ranking);

        $this->rankingSource = $ranking === null ? null : $source;
        $this->rankingFetchedAt = $ranking === null ? null : $fetchedAt;

        return $this;
    }

    public function getRankingSource(): ?RankingSource
    {
        return $this->rankingSource;
    }

    public function getRankingFetchedAt(): ?\DateTimeImmutable
    {
        return $this->rankingFetchedAt;
    }

    /**
     * Classement à mettre à jour : repris d'une saison précédente, ou prévisionnel
     */
    public function isRankingProvisional(): bool
    {
        return in_array($this->rankingSource, [RankingSource::PREVIOUS_SEASON, RankingSource::PREVISIONAL], true);
    }

    public function isInterfacs(): ?bool
    {
        return $this->interfacs;
    }

    public function setInterfacs(?bool $interfacs): static
    {
        $this->interfacs = $interfacs;

        return $this;
    }

    public function isInterclubs(): ?bool
    {
        return $this->interclubs;
    }

    public function setInterclubs(?bool $interclubs): static
    {
        $this->interclubs = $interclubs;

        return $this;
    }

    public function isCours(): ?bool
    {
        return $this->cours;
    }

    public function setCours(?bool $cours): static
    {
        $this->cours = $cours;

        return $this;
    }

    public function getAvailabilities(): ?string
    {
        return $this->availabilities;
    }

    public function setAvailabilities(?string $availabilities): static
    {
        $this->availabilities = $availabilities;

        return $this;
    }

    public function getStatus(): RegistrationStatus
    {
        return $this->status;
    }

    public function setStatus(RegistrationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === RegistrationStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === RegistrationStatus::CONFIRMED;
    }

    public function isDismissed(): bool
    {
        return $this->status === RegistrationStatus::DISMISSED;
    }

    #[Assert\Callback]
    public function validateActivities(ExecutionContextInterface $context): void
    {
        if ($this->season === null) {
            return;
        }

        if ($this->interfacs && !$this->season->hasInterfacs()) {
            $context->buildViolation('Pas d\'interfacs pour une saison d\'été.')->atPath('interfacs')->addViolation();
        }

        if ($this->interclubs && !$this->season->hasInterclubs()) {
            $context->buildViolation('Pas d\'interclubs pour une saison d\'hiver.')->atPath('interclubs')->addViolation();
        }
    }
}
