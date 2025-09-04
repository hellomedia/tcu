<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\TimeSlotRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints;

#[ORM\Entity(repositoryClass: TimeSlotRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_DATE_AND_TIME', fields: ['date', 'time'])]
#[Constraints\UniqueEntity(fields: ['date', 'time'], message: 'Il y a déjà une plage horaire qui démarre à cette heure')]
class TimeSlot implements EntityInterface
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

    #[ORM\OneToOne(mappedBy: 'timeSlot')]
    private ?InterfacMatch $match = null;

    public function __toString()
    {
        return $this->date . ' ' . $this->time . '-' . $this->endTime;
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

    public function getMatch(): ?InterfacMatch
    {
        return $this->match;
    }

    public function setMatch(?InterfacMatch $match): static
    {
        // unset the owning side of the relation if necessary
        if ($match === null && $this->match !== null) {
            $this->match->setTimeslot(null);
        }

        // set the owning side of the relation if necessary
        if ($match !== null && $match->getTimeslot() !== $this) {
            $match->setTimeslot($this);
        }

        $this->match = $match;

        return $this;
    }
}
