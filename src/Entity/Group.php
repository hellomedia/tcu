<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\MatchFormat;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     * Ordre d'affichage des poules au sein de leur saison (puis par nom en cas d'égalité)
     */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $displayOrder = 0;

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
     * NB: tri en php car le classement dépend de la saison (Registration)
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

    /**
     * Un joueur d'une poule est inscrit pour la saison de la poule (interfacs en hiver)
     */
    public static function isEligible(Player $player, Season $season): bool
    {
        $registration = $player->getRegistration($season);

        return $registration !== null && (!$season->hasInterfacs() || $registration->isInterfacs());
    }

    #[Assert\Callback]
    public function validatePlayers(ExecutionContextInterface $context): void
    {
        if ($this->season === null) {
            return;
        }

        foreach ($this->players as $player) {
            if (!self::isEligible($player, $this->season)) {
                $context->buildViolation(sprintf('%s n\'est pas inscrit%s pour %s.', $player->getName(), $this->season->hasInterfacs() ? ' aux interfacs' : '', $this->season))
                    ->atPath('players')
                    ->addViolation();
            }
        }
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

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

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

    /**
     * Matchs simples de la poule (les seuls que le round-robin génère)
     *
     * @return Collection<int, InterfacMatch>
     */
    public function getSinglesMatchs(): Collection
    {
        return $this->matchs->filter(fn (InterfacMatch $match) => $match->getFormat() === MatchFormat::SINGLES);
    }

    /**
     * Nombre de séries de la poule (1 = chaque paire se rencontre une fois), déduit des matchs existants :
     * le plus grand nombre de matchs entre deux joueurs.
     */
    public function getRounds(): int
    {
        $rounds = 1;

        foreach ($this->countMatchsByPair() as $count) {
            $rounds = max($rounds, $count);
        }

        return $rounds;
    }

    /**
     * Paires de joueurs de la poule qui n'ont pas tous leurs matchs
     * (joueur ajouté après la génération, match supprimé) : à compléter.
     *
     * @return list<array{a: Player, b: Player, missing: int}> a est le mieux classé
     */
    public function getMissingPairs(): array
    {
        if ($this->matchs->isEmpty()) {
            return []; // pas encore de matchs : il faut les générer, pas les compléter
        }

        $rounds = $this->getRounds();
        $counts = $this->countMatchsByPair();
        $players = $this->getPlayersByRanking()->toArray();
        $missing = [];

        for ($i = 0; $i < \count($players); $i++) {
            for ($j = $i + 1; $j < \count($players); $j++) {
                $count = $counts[self::pairKey($players[$i], $players[$j])] ?? 0;

                if ($count < $rounds) {
                    $missing[] = ['a' => $players[$i], 'b' => $players[$j], 'missing' => $rounds - $count];
                }
            }
        }

        return $missing;
    }

    public function getMissingMatchCount(): int
    {
        return array_sum(array_column($this->getMissingPairs(), 'missing'));
    }

    /**
     * Matchs dont un joueur ne fait plus partie de la poule
     *
     * @return Collection<int, InterfacMatch>
     */
    public function getOrphanMatchs(): Collection
    {
        return $this->matchs->filter(function (InterfacMatch $match) {
            foreach ($match->getPlayers() as $player) {
                if (!$this->players->contains($player)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @return array<string, int> nombre de matchs simples par paire de joueurs
     */
    private function countMatchsByPair(): array
    {
        $counts = [];

        foreach ($this->getSinglesMatchs() as $match) {
            $players = $match->getPlayers()->toArray();

            if (\count($players) !== 2) {
                continue;
            }

            $key = self::pairKey($players[0], $players[1]);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private static function pairKey(Player $a, Player $b): string
    {
        $ids = [$a->getId(), $b->getId()];
        sort($ids);

        return implode('-', $ids);
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
