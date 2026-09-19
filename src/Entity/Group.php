<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
class Group implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\ManyToMany(targetEntity: Player::class, inversedBy: 'groups')]
    #[ORM\OrderBy(['lastname' => 'ASC'])]
    private Collection $players;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Une poule appartient à une saison.
     * Les matchs de la poule appartiennent donc à cette saison,
     * qu'ils soient programmés ou non.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    /**
     * @var Collection<int, InterfacMatch>
     */
    #[ORM\OneToMany(targetEntity: InterfacMatch::class, mappedBy: 'group')]
    private Collection $matchs;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->matchs = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getName() ?? '';
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

    /**
     * Joueurs triés par classement (du meilleur au moins bon) pour la saison de la poule, puis par nom
     * NB: tri en php car le classement dépend de la saison (PlayerSeason)
     *
     * @return Collection<int, Player>
     */
    public function getPlayersByRanking(): Collection
    {
        $players = $this->players->toArray();

        usort($players, function (Player $a, Player $b) {
            // DESC, sans classement à la fin
            $cmp = ($b->getRankingOrder($this->season) ?? -1) <=> ($a->getRankingOrder($this->season) ?? -1);

            return $cmp !== 0 ? $cmp : $a->getLastname() <=> $b->getLastname();
        });

        return new ArrayCollection($players);
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;

        return $this;
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
    public function getMatchs(): Collection
    {
        return $this->matchs;
    }

    public function addMatch(InterfacMatch $match): static
    {
        if (!$this->matchs->contains($match)) {
            $this->matchs->add($match);
            $match->setGroup($this);
        }

        return $this;
    }

    public function removeMatch(InterfacMatch $match): static
    {
        if ($this->matchs->removeElement($match)) {
            // set the owning side to null (unless already changed)
            if ($match->getGroup() === $this) {
                $match->setGroup(null);
            }
        }

        return $this;
    }
}
