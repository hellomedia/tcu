<?php

namespace App\Entity;

use App\Repository\TimeSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeSlotRepository::class)]
class TimeSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'timeSlots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Date $date = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Time $time = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Time $endTime = null;

    #[ORM\OneToOne(mappedBy: 'timeslot', cascade: ['persist', 'remove'])]
    private ?InterfacMatch $interfacMatch = null;

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

    public function getTime(): ?Time
    {
        return $this->time;
    }

    public function setTime(?Time $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getEndTime(): ?Time
    {
        return $this->endTime;
    }

    public function setEndTime(?Time $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getInterfacMatch(): ?InterfacMatch
    {
        return $this->interfacMatch;
    }

    public function setInterfacMatch(?InterfacMatch $interfacMatch): static
    {
        // unset the owning side of the relation if necessary
        if ($interfacMatch === null && $this->interfacMatch !== null) {
            $this->interfacMatch->setTimeslot(null);
        }

        // set the owning side of the relation if necessary
        if ($interfacMatch !== null && $interfacMatch->getTimeslot() !== $this) {
            $interfacMatch->setTimeslot($this);
        }

        $this->interfacMatch = $interfacMatch;

        return $this;
    }
}
