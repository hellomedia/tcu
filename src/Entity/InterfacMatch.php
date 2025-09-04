<?php

namespace App\Entity;

use App\Repository\InterfacMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterfacMatchRepository::class)]
class InterfacMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: 'interfacMatches')]
    private Collection $players;

    #[ORM\ManyToOne(inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $playerGroup = null;

    #[ORM\OneToOne(inversedBy: 'interfacMatch', cascade: ['persist', 'remove'])]
    private ?TimeSlot $timeslot = null;

    public function __construct()
    {
        $this->players = new ArrayCollection();
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

    public function removePlayer(Player $player): static
    {
        $this->players->removeElement($player);

        return $this;
    }

    public function getPlayerGroup(): ?Group
    {
        return $this->playerGroup;
    }

    public function setPlayerGroup(?Group $playerGroup): static
    {
        $this->playerGroup = $playerGroup;

        return $this;
    }

    public function getTimeslot(): ?TimeSlot
    {
        return $this->timeslot;
    }

    public function setTimeslot(?TimeSlot $timeslot): static
    {
        $this->timeslot = $timeslot;

        return $this;
    }
}
