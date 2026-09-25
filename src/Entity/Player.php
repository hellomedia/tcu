<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\Birthyear;
use App\Enum\Gender;
use App\Enum\Ranking;
use App\Repository\PlayerRepository;
use App\Validator\AvailableAccountEmail;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[UniqueEntity(fields: ['affiliationNumber'], message: 'Ce numéro d\'affiliation est déjà utilisé par un autre joueur.')]
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

    /**
     * Numéro d'affiliation à la fédération (AFT) : identifiant officiel du joueur.
     * Permet de récupérer son classement : https://mon-classement-tennis.be/joueur/{numéro}
     */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Le numéro d\'affiliation ne contient que des chiffres.')]
    private ?string $affiliationNumber = null;

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
     * Inscriptions par saison : classement, interfacs, interclubs, cours
     *
     * @var Collection<int, PlayerSeason>
     */
    #[ORM\OneToMany(targetEntity: PlayerSeason::class, mappedBy: 'player', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $seasons;

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

    /**
     * Email saisi dans un formulaire, pour le compte (User) lié au joueur. Pas une colonne :
     * c'est PlayerAccountManager qui crée le compte ou change son email à l'enregistrement.
     */
    #[Assert\Email]
    #[AvailableAccountEmail]
    private ?string $accountEmail = null;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->seasons = new ArrayCollection();
        $this->matchParticipations = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getNameWithRanking(?Season $season): string
    {
        $ranking = $this->getRanking($season);

        return $ranking ? $this->getName() . ' - ' . $ranking->value : $this->getName();
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

    /**
     * @return Collection<int, PlayerSeason>
     */
    public function getSeasons(): Collection
    {
        return $this->seasons;
    }

    public function addSeason(PlayerSeason $playerSeason): static
    {
        if (!$this->seasons->contains($playerSeason)) {
            $this->seasons->add($playerSeason);
            $playerSeason->setPlayer($this);
        }

        return $this;
    }

    public function removeSeason(PlayerSeason $playerSeason): static
    {
        $this->seasons->removeElement($playerSeason); // orphanRemoval

        return $this;
    }

    /**
     * Inscription du joueur pour une saison
     *
     * @param bool $includeDismissed une inscription pré-remplie écartée n'est pas une inscription
     */
    public function getRegistration(?Season $season, bool $includeDismissed = false): ?PlayerSeason
    {
        if ($season === null) {
            return null;
        }

        foreach ($this->seasons as $playerSeason) {
            if ($playerSeason->getSeason() === $season) {
                return $playerSeason->isDismissed() && !$includeDismissed ? null : $playerSeason;
            }
        }

        return null;
    }

    /**
     * Le classement change à chaque saison
     */
    public function getRanking(?Season $season): ?Ranking
    {
        return $this->getRegistration($season)?->getRanking();
    }

    public function getRankingOrder(?Season $season): ?int
    {
        return $this->getRegistration($season)?->getRankingOrder();
    }

    public function getAffiliationNumber(): ?string
    {
        return $this->affiliationNumber;
    }

    public function setAffiliationNumber(?string $affiliationNumber): static
    {
        // champ vide du formulaire => null (index unique)
        $affiliationNumber = $affiliationNumber !== null ? trim($affiliationNumber) : null;
        $this->affiliationNumber = $affiliationNumber === '' ? null : $affiliationNumber;

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

    /**
     * @return Collection<int, Group>
     */
    public function getGroupsForSeason(?Season $season): Collection
    {
        return $this->groups->filter(function (Group $group) use ($season) {
            return $group->getSeason() === $season;
        });
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
     * @param Season|null $season null = toutes saisons confondues
     *
     * @return Collection<int, InterfacMatch>
     */
    public function getMatchs(?Season $season = null): Collection
    {
        $matchs = $this->matchParticipations->map(function(MatchParticipant $participant) {
            return $participant->getMatch();
        });

        if ($season !== null) {
            $matchs = $matchs->filter(function (InterfacMatch $match) use ($season) {
                return $match->getSeason() === $season;
            });
        }

        $sorted = $matchs->toArray();

        usort($sorted, function (InterfacMatch $a, InterfacMatch $b) {
            $cmp = $a->getDateEntityDate() <=> $b->getDateEntityDate();
            if ($cmp !== 0) {
                return $cmp;
            }
            // if dates are equal, compare by time
            return $a->startsAt() <=> $b->startsAt();
        });

        return new ArrayCollection($sorted);
    }

    /**
     * Scheduled matchs
     * all: past and future
     * 
     * @return Collection<int, InterfacMatch>
     */
    public function getScheduledMatchs(?Season $season = null): Collection
    {
        return $this->getMatchs($season)->filter(function(InterfacMatch $match) {
            return $match->isScheduled();
        });
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getNonScheduledMatchs(?Season $season = null): Collection
    {
        return $this->getMatchs($season)->filter(function (InterfacMatch $match) {
            return $match->isScheduled() == false;
        });
    }

    /**
     * Upcoming = scheduled + in future
     * 
     * @return Collection<int, InterfacMatch>
     */
    public function getUpcomingMatchs(): Collection
    {
        return $this->getMatchs()->filter(function(InterfacMatch $match) {
            return $match->isUpcoming();
        });
    }

    /**
     * @return Collection<int, Date>
     */
    public function getScheduledMatchsDates(): Collection
    {
        return $this->getScheduledMatchs()->map(function(InterfacMatch $match): Date {
            return $match->getDate();
        });
    }

    /**
     * Not confirmed by user or admin
     * 
     * @return Collection<int, InterfacMatch>
     */
    public function getUnconfirmedUpcomingMatchs(): Collection
    {
        return $this->getUpcomingMatchs()->filter(function (InterfacMatch $match) {
            return $match->isConfirmed($this->user) === false;
        });
    }

    /**
     * Confirmed by user or by admin
     * 
     * @return Collection<int, InterfacMatch>
     */
    public function getConfirmedUpcomingMatchs(): Collection
    {
        return $this->getUpcomingMatchs()->filter(function (InterfacMatch $match) {
            return $match->isConfirmed($this->user) === true;
        });
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

    /**
     * Email du compte lié, ou email saisi mais pas encore enregistré
     */
    public function getAccountEmail(): ?string
    {
        return $this->accountEmail ?? $this->user?->getEmail();
    }

    /**
     * Email saisi : à enregistrer par PlayerAccountManager::sync().
     * Vide = on ne touche pas au compte.
     */
    public function setAccountEmail(?string $accountEmail): static
    {
        $this->accountEmail = trim((string) $accountEmail) ?: null;

        return $this;
    }

    public function hasAccount(): bool
    {
        return $this->user !== null;
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
