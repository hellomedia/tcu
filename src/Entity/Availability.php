<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Disponibilité d'un joueur (inscription) pour un créneau horaire d'une date : réponse explicite.
 *
 * Une ligne = une réponse (disponible ou pas). Pas de ligne = pas renseigné.
 * Clé par date + heure de début, pas par Slot : le terrain est indifférent au joueur
 * et les créneaux peuvent être recréés.
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\UniqueConstraint(name: 'availability_unique', columns: ['registration_id', 'date_id', 'starts_at'])]
class Availability implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Registration $registration = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Date $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column]
    private bool $available = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegistration(): ?Registration
    {
        return $this->registration;
    }

    public function setRegistration(Registration $registration): static
    {
        $this->registration = $registration;

        return $this;
    }

    public function getDate(): ?Date
    {
        return $this->date;
    }

    public function setDate(Date $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): static
    {
        $this->available = $available;

        return $this;
    }

    /**
     * Clé "date-heure" utilisée par AvailabilityGrid
     */
    public function getKey(): string
    {
        return self::key($this->date, $this->startsAt);
    }

    public static function key(Date $date, \DateTimeInterface $startsAt): string
    {
        return $date->getId() . '-' . $startsAt->format('H:i');
    }
}
