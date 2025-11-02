<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\MatchFormat;
use App\Enum\Side;
use App\Repository\InterfacMatchRepository;
use DateTimeImmutable;
use DateTimeInterface;
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

    #[ORM\Column(enumType: MatchFormat::class, options: ['default' => MatchFormat::SINGLES->value])]
    private ?MatchFormat $format = MatchFormat::SINGLES;

    #[ORM\ManyToOne(inversedBy: 'matchs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\OneToOne(mappedBy: 'match', cascade: ['remove'])]
    private ?Booking $booking = null;

    /**
     * @var Collection<int, MatchParticipant>
     */
    #[ORM\OneToMany(targetEntity: MatchParticipant::class, mappedBy: 'match',  cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    #[ORM\OneToOne(mappedBy: 'match', cascade: ['persist', 'remove'])]
    private ?MatchResult $result = null;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getPlayersAsString();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormat(): ?MatchFormat
    {
        return $this->format;
    }

    public function setFormat(MatchFormat $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * @return Collection<int, Player>
     */
    public function getPlayers(): Collection
    {
        return $this->participants->map(
            fn(MatchParticipant $participant) => $participant->getPlayer()
        );
    }

    public function getPlayersAsString(): string
    {
        if ($this->participants->isEmpty()) {
            return '';
        }
    
        return implode(' - ', $this->participants->map(fn(MatchParticipant $participant) => $participant->getPlayer()->getName())->toArray());
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

    public function getSlot(): ?Slot
    {
        return $this->booking?->getSlot();
    }

    public function isScheduled(): bool
    {
        return $this->booking != null;
    }

    public function isUpcoming(): bool
    {
        if ($this->booking == null) {
            return false;
        }

        return $this->booking->getDate()->isFuture();
    }

    public function isPast(): bool
    {
        if ($this->booking == null) {
            return false;
        }

        return $this->booking->getDate()->isPast();
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

    public function startsAt(): ?DateTimeImmutable
    {
        return $this->booking?->getSlot()->getStartsAt();
    }

    public function getCourt(): ?Court
    {
        return $this->booking?->getCourt();
    }

    public function getDate(): ?Date
    {
        return $this->getBooking()?->getSlot()?->getDate();
    }

    public function getDateEntityDate(): ?DateTimeInterface
    {
        return $this->getDate()?->getDate();
    }

    /**
     * @return Collection<int, MatchParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(MatchParticipant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setMatch($this);
        }

        return $this;
    }

    public function removeParticipant(MatchParticipant $participant): static
    {
        if ($this->participants->removeElement($participant)) {
            // set the owning side to null (unless already changed)
            if ($participant->getMatch() === $this) {
                $participant->setMatch(null);
            }
        }

        return $this;
    }

    public function replaceParticipantsForSide(Side $side, Player ...$players): void
    {
        foreach ($this->participants->toArray() as $p) {
            if ($p->getSide() === $side) {
                $this->participants->removeElement($p); // orphanRemoval
            }
        }
    
        foreach ($players as $player) {
            $this->participants->add(
                (new MatchParticipant())->setMatch($this)->setSide($side)->setPlayer($player)
            );
        }
    }

    /**
     * @return array<Player>
     */
    public function getPlayersForSide(Side $side): ?array
    {
        $sideParticipants = $this->participants->filter(function (MatchParticipant $participant) use ($side) {
            return $participant->getSide() === $side;
        });

        $sidePlayers = $sideParticipants->map(function(MatchParticipant $participant) {
            return $participant->getPlayer();
        })->toArray();

        usort($sidePlayers, function (Player $player1, Player $player2) {
            return $player1->getLastname() <=> $player2->getLastname();
        });

        return $sidePlayers;
    }

    /**
     * @return array<MatchParticipant>
     */
    public function getParticipantsForSide(Side $side): ?array
    {
        $sideParticipants = $this->participants->filter(function (MatchParticipant $participant) use ($side) {
            return $participant->getSide() === $side;
        })->toArray();

        usort($sideParticipants, function (MatchParticipant $participant1, MatchParticipant $participant2) {
            return $participant1->getPlayer()->getLastname() <=> $participant2->getPlayer()->getLastname();
        });

        return $sideParticipants;
    }

    public function getResult(): ?MatchResult
    {
        return $this->result;
    }

    public function setResult(MatchResult $result): static
    {
        // set the owning side of the relation if necessary
        if ($result->getMatch() !== $this) {
            $result->setMatch($this);
        }

        $this->result = $result;

        return $this;
    }

    public function getParticipant(User $user): ?MatchParticipant
    {
        foreach($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                $found = $participant;
                break;
            }
        }

        return $found ?? null;
    }

    public function isParticipant(User $user): bool
    {
        return $this->getParticipant($user) !== null;
    }

    public function getConfirmationInfo(User $user): ?ParticipantConfirmationInfo
    {
        return $this->getParticipant($user)?->getConfirmationInfo();
    }

    /**
     * @return Collection<int, ParticipantConfirmationInfo>
     */
    public function getConfirmationInfos(): Collection
    {
        return $this->participants
            // filter out null values
            ->filter(fn(MatchParticipant $participant) => $participant->getConfirmationInfo() !== null)
            // fill collection with confirmation info
            ->map(fn(MatchParticipant $participant) => $participant->getConfirmationInfo());
    }

    /**
     * NB: When we are in user area, we tend to check the confirmation info
     * from the match (user point of view) with user parameters.
     * 
     * When we are in admin are, we tend to check the confirmation info
     * from the participant (admin point of view).
     * ==> participant::isConfirmed()
     */
    public function isConfirmedByUser(User $user): bool
    {
        return (bool) $this->getConfirmationInfo($user)?->isConfirmedByPlayer();
    }

    public function isConfirmedByAdmin(User $user): bool
    {
        return (bool) $this->getConfirmationInfo($user)?->isConfirmedByAdmin();
    }

    public function isConfirmed(User $user): bool
    {
        return (bool) $this->getConfirmationInfo($user)?->isConfirmed();
    }
}
