<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'booking')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]  // <- DB will delete bookings when Slot is deleted
    private ?Slot $slot = null;

    #[ORM\Column(enumType: BookingType::class)]
    private ?BookingType $type = null;

    #[ORM\OneToOne(inversedBy: 'booking')]
    #[ORM\JoinColumn(nullable: true)]
    private ?InterfacMatch $match = null;

    public function __toString()
    {
        return $this->slot;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlot(): ?Slot
    {
        return $this->slot;
    }

    public function setSlot(Slot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getType(): ?BookingType
    {
        return $this->type;
    }

    public function setType(BookingType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMatch(): ?InterfacMatch
    {
        return $this->match;
    }

    public function setMatch(?InterfacMatch $match): static
    {
        $this->match = $match;

        return $this;
    }

    public function getTimeRange(): string
    {
        return $this->slot->getTimeRange();
    }

    public function getCourt(): Court
    {
        return $this->slot->getCourt();
    }
}
