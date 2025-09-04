<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: 'groups')]
    private Collection $players;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * @var Collection<int, InterfacMatch>
     */
    #[ORM\OneToMany(targetEntity: InterfacMatch::class, mappedBy: 'playerGroup')]
    private Collection $matches;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->matches = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getMatches(): Collection
    {
        return $this->matches;
    }

    public function addMatch(InterfacMatch $match): static
    {
        if (!$this->matches->contains($match)) {
            $this->matches->add($match);
            $match->setPlayerGroup($this);
        }

        return $this;
    }

    public function removeMatch(InterfacMatch $match): static
    {
        if ($this->matches->removeElement($match)) {
            // set the owning side to null (unless already changed)
            if ($match->getPlayerGroup() === $this) {
                $match->setPlayerGroup(null);
            }
        }

        return $this;
    }
}
