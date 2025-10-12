<?php

namespace App\Entity;

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

        return $this;
    }

    public function getSet1B(): ?int
    {
        return $this->set1B;
    }

    public function setSet1B(?int $set1B): static
    {
        $this->set1B = $set1B;

        return $this;
    }

    public function getSet2A(): ?int
    {
        return $this->set2A;
    }

    public function setSet2A(?int $set2A): static
    {
        $this->set2A = $set2A;

        return $this;
    }

    public function getSet2B(): ?int
    {
        return $this->set2B;
    }

    public function setSet2B(?int $set2B): static
    {
        $this->set2B = $set2B;

        return $this;
    }

    public function getSet3A(): ?int
    {
        return $this->set3A;
    }

    public function setSet3A(?int $set3A): static
    {
        $this->set3A = $set3A;

        return $this;
    }

    public function getSet3B(): ?int
    {
        return $this->set3B;
    }

    public function setSet3B(?int $set3B): static
    {
        $this->set3B = $set3B;

        return $this;
    }

    public function getPointsA(): ?int
    {
        return $this->pointsA;
    }

    public function setPointsA(?int $pointsA): static
    {
        $this->pointsA = $pointsA;

        return $this;
    }

    public function getPointsB(): ?int
    {
        return $this->pointsB;
    }

    public function setPointsB(?int $pointsB): static
    {
        $this->pointsB = $pointsB;

        return $this;
    }
}
