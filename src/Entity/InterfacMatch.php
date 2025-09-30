<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\InterfacMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterfacMatchRepository::class)]
class InterfacMatch implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: 'matchs')]
    private Collection $players;

    #[ORM\ManyToOne(inversedBy: 'matchs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\OneToOne(mappedBy: 'match', cascade: ['remove'])]
    private ?Booking $booking = null;

    public function __construct()
    {
        $this->players = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getPlayersAsString();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Player>
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
        }

        return $this;
    }

    public function getPlayersAsString(): string
    {
        if ($this->players->isEmpty()) {
            return '';
        }
    
        return implode(' - ', $this->players->map(fn($player) => $player->getFullName())->toArray());
    }

    public function removePlayer(Player $player): static
    {
        $this->players->removeElement($player);

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function isScheduled(): bool
    {
        return $this->booking != null;
    }

    public function setBooking(?Booking $booking): static
    {
        // unset the owning side of the relation if necessary
        if ($booking === null && $this->booking !== null) {
            $this->booking->setMatch(null);
        }

        // set the owning side of the relation if necessary
        if ($booking !== null && $booking->getMatch() !== $this) {
            $booking->setMatch($this);
        }

        $this->booking = $booking;

        return $this;
    }

    public function getTimeRange(): ?string
    {
        return $this->booking?->getTimeRange();
    }

    public function getCourt(): ?Court
    {
        return $this->booking?->getCourt();
    }
}
