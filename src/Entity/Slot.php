<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\SlotRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints;

#[ORM\Entity(repositoryClass: SlotRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_DATE_START_COURT', fields: ['date', 'startsAt', 'court'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_DATE_END_COURT', fields: ['date', 'endsAt', 'court'])]
#[Constraints\UniqueEntity(fields: ['startsAt', 'date', 'court'], message: 'Il y a déjà une plage horaire démarrant à cette heure')]
#[Constraints\UniqueEntity(fields: ['endsAt', 'date', 'court'], message: 'Il y a déjà une plage horaire terminant à cette heure')]
class Slot implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]  // <- DB will delete slots when Date is deleted
    private ?Date $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")] // <- DB will delete slots when Court is deleted
    private ?Court $court = null;

    #[ORM\OneToOne(mappedBy: 'slot')]
    private ?Booking $booking = null;

    public function __toString()
    {
        return $this->date . ' • ' . $this->getTimeRange() . ' • ' . $this->getCourt();
    }

    public function getTimeRange()
    {
        return $this->startsAt->format('H:i'). '-' . $this->endsAt->format('H:i');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?Date
    {
        return $this->date;
    }

    public function setDate(?Date $date): static
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

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getCourt(): ?Court
    {
        return $this->court;
    }

    public function setCourt(?Court $court): static
    {
        $this->court = $court;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        if ($booking == null) {
            $this->booking = null;
            
            return $this;
        }

        // set the owning side of the relation if necessary
        if ($booking->getSlot() !== $this) {
            $booking->setSlot($this);
        }

        $this->booking = $booking;

        return $this;
    }

    public function getMatch(): ?InterfacMatch
    {
        return $this->booking?->getMatch();
    }

    public function getPlayers(): ?Collection
    {
        return $this->booking?->getMatch()?->getPlayers()?->map(fn ($player) => $player->getName());
    }
}
