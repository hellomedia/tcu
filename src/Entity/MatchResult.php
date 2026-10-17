<?php

namespace App\Entity;

use App\Enum\Side;
use App\Repository\MatchResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchResultRepository::class)]
class MatchResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'result', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?InterfacMatch $match = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set1A = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set1B = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set2A = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set2B = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set3A = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $set3B = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $pointsA = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $pointsB = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): ?InterfacMatch
    {
        return $this->match;
    }

    public function setMatch(InterfacMatch $match): static
    {
        $this->match = $match;

        return $this;
    }

    public function getSet1A(): ?int
    {
        return $this->set1A;
    }

    public function setSet1A(?int $set1A): static
    {
        $this->set1A = $set1A;

        $this->updatePoints();

        return $this;
    }

    public function getSet1B(): ?int
    {
        return $this->set1B;
    }

    public function setSet1B(?int $set1B): static
    {
        $this->set1B = $set1B;

        $this->updatePoints();

        return $this;
    }

    public function getSet2A(): ?int
    {
        return $this->set2A;
    }

    public function setSet2A(?int $set2A): static
    {
        $this->set2A = $set2A;

        $this->updatePoints();

        return $this;
    }

    public function getSet2B(): ?int
    {
        return $this->set2B;
    }

    public function setSet2B(?int $set2B): static
    {
        $this->set2B = $set2B;

        $this->updatePoints();

        return $this;
    }

    public function getSet3A(): ?int
    {
        return $this->set3A;
    }

    public function setSet3A(?int $set3A): static
    {
        $this->set3A = $set3A;

        $this->updatePoints();

        return $this;
    }

    public function getSet3B(): ?int
    {
        return $this->set3B;
    }

    public function setSet3B(?int $set3B): static
    {
        $this->set3B = $set3B;

        $this->updatePoints();

        return $this;
    }

    public function getPointsA(): ?int
    {
        return $this->pointsA;
    }

    public function getPointsB(): ?int
    {
        return $this->pointsB;
    }

    /**
     * Sets played, as [games A, games B]
     *
     * @return array<array{int, int}>
     */
    public function getSets(): array
    {
        $sets = [];

        foreach ([[$this->set1A, $this->set1B], [$this->set2A, $this->set2B], [$this->set3A, $this->set3B]] as [$a, $b]) {
            if ($a !== null || $b !== null) {
                $sets[] = [(int) $a, (int) $b];
            }
        }

        return $sets;
    }

    /**
     * Side that won more sets (null if not decided)
     */
    public function getWinner(): ?Side
    {
        $setsA = $setsB = 0;

        foreach ($this->getSets() as [$a, $b]) {
            $setsA += $a > $b ? 1 : 0;
            $setsB += $b > $a ? 1 : 0;
        }

        return match (true) {
            $setsA > $setsB => Side::A,
            $setsB > $setsA => Side::B,
            default => null,
        };
    }

    /**
     * Points of each side, summed over the sets (the match may have been stopped when the time was over):
     * - set won 7/6: 2 points for the winner, 1 for the loser
     * - set won with any other score: 2 points for the winner
     * - unfinished set, led by 2 games or more: 1 point for the leader
     * - unfinished set, tied or led by 1 game only (players should not stop there, but it happens): 1 point each
     */
    private function updatePoints(): void
    {
        $this->pointsA = $this->pointsB = 0;

        foreach ($this->getSets() as [$a, $b]) {
            [$pointsA, $pointsB] = self::setPoints($a, $b);
            $this->pointsA += $pointsA;
            $this->pointsB += $pointsB;
        }
    }

    /**
     * @return array{int, int} points of A and B for a set
     */
    private static function setPoints(int $a, int $b): array
    {
        if ($a < $b) {
            return array_reverse(self::setPoints($b, $a));
        }

        // A leads or tied: a set is won at 6 games with 2 games ahead, or at 7 games (7/5, 7/6)
        // (also covers a super tie-break, 10/8)
        if ($a >= 6 && ($a - $b >= 2 || $a === 7)) {
            return [2, $a === 7 && $b === 6 ? 1 : 0];
        }

        return match (true) {
            $a - $b >= 2 => [1, 0],
            default => [1, 1],
        };
    }
}
