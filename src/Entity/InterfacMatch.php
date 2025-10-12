<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\MatchFormat;
use App\Enum\Side;
use App\Repository\InterfacMatchRepository;
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
    
        return implode(' - ', $this->participants->map(fn(MatchParticipant $participant) => $participant->getPlayer()->getFullName())->toArray());
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

    public function isScheduled(): bool
    {
        return $this->booking != null;
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

    public function getPlayersForSide(Side $side): ?array
    {
        $filtered = $this->participants->filter(function (MatchParticipant $participant) use ($side) {
            return $participant->getSide() === $side;
        });

        $sorted = $filtered->toArray();

        usort($sorted, function (MatchParticipant $a, MatchParticipant $b) {
            return $a->getPlayer()->getLastname() <=> $b->getPlayer()->getLastname();
        });

        return $sorted;
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
}
