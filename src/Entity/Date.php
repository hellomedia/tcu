<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\DateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use IntlDateFormatter;

/**
 * Could do without, but it seems a bit cleaner..
 * Somewhat useful for handling slots by batch
 * ie: remove all slots on a certain date
 */
#[ORM\Entity(repositoryClass: DateRepository::class)]
class Date implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeInterface $date = null;

    /**
     * @var Collection<int, Slot>
     * 
     * orphanRemoval: true ensures that if you remove a Slot from $date->getSlots()
     * and keep your add/remove helpers in sync, Doctrine will DELETE that Slot
     * (see removeSlot() )
     */
    #[ORM\OneToMany(targetEntity: Slot::class, mappedBy: 'date', orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $slots;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
    }

    public function __toString()
    {
        $fmt = new IntlDateFormatter(
            locale: 'fr-FR',
            pattern: 'EEEE dd/MM/YY'
        );
        return ucfirst($fmt->format($this->date));
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
     * @return Collection<int, Slot>
     */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    /**
     * @return Collection<int, Slot>
     */
    public function getSlotsByCourt(Court $court): Collection
    {
        return $this->slots->filter(static fn($slot) => $slot->getCourt() === $court);
    }

    public function addSlot(Slot $slot): static
    {
        if (!$this->slots->contains($slot)) {
            $this->slots->add($slot);
            $slot->setDate($this);
        }

        return $this;
    }

    /**
     * Removing a slot from a date means the slot should be removed from DB
     * ==> orphan removal
     */
    public function removeSlot(Slot $slot): static
    {
        if ($this->slots->removeElement($slot)) {
            // set the owning side to null (unless already changed)
            if ($slot->getDate() === $this) {
                $slot->setDate(null); // triggers orphanRemoval -> DELETE (no UPDATE to NULL generated)
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InterfacMatch>
     * 
     * Should be sorted by startsAt ASC since slots are sorted that way
     */
    public function getMatchs(): Collection
    {
        return $this->slots
            ->map(static fn (Slot $slot) => $slot->getBooking()?->getMatch())
            ->filter(static fn($v) => null !== $v)
        ;
    }

    /**
     * @return Collection<int, InterfacMatch>
     * 
     * Should be sorted by startsAt ASC since slots are sorted that way
     */
    public function getMatchsByCourt(Court $court): Collection
    {
        return $this->getSlotsByCourt($court)
            ->map(static fn (Slot $slot) => $slot->getBooking()?->getMatch())
            ->filter(static fn($v) => null !== $v)
        ;
    }

    /**
     * @return Collection<int, InterfacMatch>
     * 
     * Should be sorted by startsAt ASC since slots are sorted that way
     */
    public function getMatchsByGroup(Group $group): Collection
    {
        return $this->slots
            ->map(static fn (Slot $slot) => $slot->getBooking()?->getMatch())
            ->filter(static fn($match) => $match !== null && $match->getGroup() === $group)
        ;
    }
}
