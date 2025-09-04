<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\Birthyear;
use App\Enum\Gender;
use App\Enum\Ranking;
use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(nullable: true, enumType: Ranking::class)]
    private ?Ranking $ranking = null;

    #[ORM\Column(enumType: Gender::class)]
    private ?Gender $gender = null;

    #[ORM\Column(nullable: true, enumType: Birthyear::class)]
    private ?Birthyear $birthyear = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    /**
     * @var Collection<int, Group>
     */
    #[ORM\ManyToMany(targetEntity: Group::class, mappedBy: 'players')]
    private Collection $groups;

    /**
     * @var Collection<int, InterfacMatch>
     */
    #[ORM\ManyToMany(targetEntity: InterfacMatch::class, mappedBy: 'players')]
    private Collection $matchs;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->matchs = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->firstname . ' ' . ($this->lastname ?? '') . ' ' . $this->ranking->value;
    }

    public function getFullName(): string
    {
        return $this->firstname . ' ' . ($this->lastname ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getRanking(): ?Ranking
    {
        return $this->ranking;
    }

    public function setRanking(?Ranking $ranking): static
    {
        $this->ranking = $ranking;

        return $this;
    }

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getBirthyear(): ?Birthyear
    {
        return $this->birthyear;
    }

    public function setBirthyear(?Birthyear $birthyear): static
    {
        $this->birthyear = $birthyear;

        return $this;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->addPlayer($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        if ($this->groups->removeElement($group)) {
            $group->removePlayer($this);
        }

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
            $match->addPlayer($this);
        }

        return $this;
    }

    public function removeMatch(InterfacMatch $match): static
    {
        if ($this->matchs->removeElement($match)) {
            $match->removePlayer($this);
        }

        return $this;
    }
}
