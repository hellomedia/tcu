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

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rankingOrder = null; // <-- for sorting

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

    #[ORM\Column(nullable: true)]
    private ?bool $interfacs = null;

    #[ORM\Column(nullable: true)]
    private ?bool $interclubs = null;

    #[ORM\Column(nullable: true)]
    private ?bool $cours = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $availabilities = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    /**
     * @var Collection<int, MatchParticipant>
     */
    #[ORM\OneToMany(targetEntity: MatchParticipant::class, mappedBy: 'player')]
    private Collection $matchParticipations;

    #[ORM\OneToOne(mappedBy: 'player', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->matchParticipations = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getNameWithRanking(): string
    {
        return $this->getName() . ' - ' . $this->ranking->value;
    }

    public function getName(): string
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

        // Update the sorting order
        if ($ranking === null) {
            $this->rankingOrder = null;
        } else {
            // Get the array of all enum cases in their declared order
            $order = array_flip(array_column(Ranking::cases(), 'name'));
            $this->rankingOrder = $order[$ranking->name] ?? null;
        }

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
     * @return Collection<int, MatchParticipant>
     */
    public function getMatchParticipations(): Collection
    {
        return $this->matchParticipations;
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getMatchs(): Collection
    {
        return $this->matchParticipations->map(function(MatchParticipant $participant) {
            return $participant->getMatch();
        });
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getScheduledMatchs(): Collection
    {
        $filtered = $this->getMatchs()->filter(function(InterfacMatch $match) {
            return $match->isScheduled();
        });

        $sorted = $filtered->toArray();

        usort($sorted, function (InterfacMatch $a, InterfacMatch $b) {
            return $a->getDateEntityDate() <=> $b->getDateEntityDate();
        });

        return new ArrayCollection($sorted);
    }

    /**
     * @return Collection<int, Date>
     */
    public function getScheduledMatchsDates(): Collection
    {
        $scheduledMatchs = $this->getScheduledMatchs();

        $dates = $scheduledMatchs->map(function(InterfacMatch $match): Date {
            return $match->getDate();
        });

        return $dates;
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getNonScheduledMatchs(): Collection
    {
        return $this->getMatchs()->filter(function(InterfacMatch $match) {
            return $match->isScheduled() == false;
        });
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getUnconfirmedScheduledMatchs(): Collection
    {
        $filtered = $this->getScheduledMatchs()->filter(function (InterfacMatch $match) {
            return $match->isConfirmedByUser($this->user) === false;
        });

        $sorted = $filtered->toArray();

        usort($sorted, function (InterfacMatch $a, InterfacMatch $b) {
            return $a->getDateEntityDate() <=> $b->getDateEntityDate();
        });

        return new ArrayCollection($sorted);
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getConfirmedScheduledMatchs(): Collection
    {
        $filtered = $this->getScheduledMatchs()->filter(function (InterfacMatch $match) {
            return $match->isConfirmedByUser($this->user) === true;
        });

        $sorted = $filtered->toArray();

        usort($sorted, function (InterfacMatch $a, InterfacMatch $b) {
            return $a->getDateEntityDate() <=> $b->getDateEntityDate();
        });

        return new ArrayCollection($sorted);
    }

    public function isInterfacs(): ?bool
    {
        return $this->interfacs;
    }

    public function setInterfacs(?bool $interfacs): static
    {
        $this->interfacs = $interfacs;

        return $this;
    }

    public function isInterclubs(): ?bool
    {
        return $this->interclubs;
    }

    public function setInterclubs(?bool $interclubs): static
    {
        $this->interclubs = $interclubs;

        return $this;
    }

    public function isCours(): ?bool
    {
        return $this->cours;
    }

    public function setCours(?bool $cours): static
    {
        $this->cours = $cours;

        return $this;
    }

    public function getAvailabilities(): ?string
    {
        return $this->availabilities;
    }

    public function setAvailabilities(?string $availabilities): static
    {
        $this->availabilities = $availabilities;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setPlayer(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getPlayer() !== $this) {
            $user->setPlayer($this);
        }

        $this->user = $user;

        return $this;
    }
}
