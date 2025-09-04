<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\DateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use IntlDateFormatter;

#[ORM\Entity(repositoryClass: DateRepository::class)]
class Date implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    /**
     * @var Collection<int, TimeSlot>
     */
    #[ORM\OneToMany(targetEntity: TimeSlot::class, mappedBy: 'date')]
    private Collection $timeSlots;

    public function __construct()
    {
        $this->timeSlots = new ArrayCollection();
    }

    public function __toString()
    {
        $fmt = new IntlDateFormatter(
            locale: 'fr-FR',
            pattern: 'EEEE dd/MM/YY'
        );
        return $fmt->format($this->date);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection<int, TimeSlot>
     */
    public function getTimeSlots(): Collection
    {
        return $this->timeSlots;
    }

    public function addTimeSlot(TimeSlot $timeSlot): static
    {
        if (!$this->timeSlots->contains($timeSlot)) {
            $this->timeSlots->add($timeSlot);
            $timeSlot->setDate($this);
        }

        return $this;
    }

    public function removeTimeSlot(TimeSlot $timeSlot): static
    {
        if ($this->timeSlots->removeElement($timeSlot)) {
            // set the owning side to null (unless already changed)
            if ($timeSlot->getDate() === $this) {
                $timeSlot->setDate(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getMatches(): Collection
    {
        return $this->timeSlots
            ->map(static fn (TimeSlot $slot) => $slot->getMatch())
            ->filter(static fn($v) => null !== $v)
        ;
    }
}
