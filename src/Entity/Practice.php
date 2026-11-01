<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Repository\PracticeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Entraînement : des joueurs de niveau proche (une ou plusieurs poules) jouent librement ensemble.
 *
 * - Occupe un ou plusieurs créneaux d'une même date (plusieurs terrains et/ou heures qui se suivent) : voir $bookings
 * - Visible seulement dans l'espace des joueurs des poules liées, pas dans le planning public (pour l'instant)
 * - Les joueurs répondent "j'y serai / je ne viens pas" (PracticeParticipant, créé à la demande)
 * - Annulé : reste affiché barré ; supprimé : disparaît sans trace
 */
#[ORM\Entity(repositoryClass: PracticeRepository::class)]
class Practice implements EntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Les dates ne sont pas liées aux saisons et un entraînement peut n'avoir aucune poule :
     * la saison est portée par l'entraînement lui-même (filtres de l'admin, notifications)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    /**
     * @var Collection<int, Group>
     */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'practice_group')]
    #[ORM\OrderBy(['displayOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $groups;

    /**
     * Intitulé libre, à la place des noms des poules (obligatoire sans poule)
     */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'practice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $bookings;

    /**
     * @var Collection<int, PracticeParticipant>
     */
    #[ORM\OneToMany(targetEntity: PracticeParticipant::class, mappedBy: 'practice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->participants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    public function getId(): ?int
    {
        return $this->id;
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
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        $this->groups->removeElement($group);

        return $this;
    }

    public function hasGroup(Group $group): bool
    {
        return $this->groups->contains($group);
    }

    /**
     * Intitulé affiché : le titre s'il y en a un, sinon les poules ("Poule 1 & Poule 2")
     */
    public function getLabel(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title;
        }

        return implode(' & ', $this->groups->map(fn (Group $group) => (string) $group)->toArray());
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $title = $title !== null ? trim($title) : null;
        $this->title = $title === '' ? null : $title;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = $note !== null ? trim($note) : null;
        $this->note = $note === '' ? null : $note;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function cancel(): static
    {
        $this->cancelledAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->cancelledAt = null;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setPractice($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // orphanRemoval : la réservation est supprimée, le créneau est libéré
            if ($booking->getPractice() === $this) {
                $booking->setPractice(null);
                $booking->getSlot()?->setBooking(null);
            }
        }

        return $this;
    }

    /**
     * Créneaux occupés, par heure puis par terrain
     *
     * @return Slot[]
     */
    public function getSlots(): array
    {
        $slots = $this->bookings->map(fn (Booking $booking) => $booking->getSlot())->filter(fn (?Slot $slot) => $slot !== null)->toArray();

        usort($slots, fn (Slot $a, Slot $b) => [$a->getStartsAt(), (string) $a->getCourt()] <=> [$b->getStartsAt(), (string) $b->getCourt()]);

        return array_values($slots);
    }

    /**
     * Les créneaux appartiennent-ils tous à cette date ? (un entraînement = une seule date)
     */
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->groups->isEmpty() && ($this->title === null || $this->title === '')) {
            $context->buildViolation('Indiquez un intitulé quand aucune poule n\'est liée.')
                ->atPath('title')
                ->addViolation();
        }

        if ($this->bookings->isEmpty()) {
            $context->buildViolation('Choisissez au moins un créneau.')
                ->atPath('bookings')
                ->addViolation();
        }

        $dates = [];
        foreach ($this->getSlots() as $slot) {
            $dates[spl_object_id($slot->getDate())] = true;
        }
        if (\count($dates) > 1) {
            $context->buildViolation('Les créneaux d\'un entraînement doivent être à la même date.')
                ->atPath('bookings')
                ->addViolation();
        }

        foreach ($this->groups as $group) {
            if ($this->season !== null && $group->getSeason() !== $this->season) {
                $context->buildViolation(sprintf('La poule %s n\'est pas de la saison %s.', $group, $this->season))
                    ->atPath('groups')
                    ->addViolation();
            }
        }
    }

    public function isScheduled(): bool
    {
        return !$this->bookings->isEmpty();
    }

    public function getDate(): ?Date
    {
        $slots = $this->getSlots();

        return $slots === [] ? null : $slots[0]->getDate();
    }

    public function startsAt(): ?\DateTimeImmutable
    {
        $slots = $this->getSlots();

        return $slots === [] ? null : $slots[0]->getStartsAt();
    }

    public function endsAt(): ?\DateTimeImmutable
    {
        $end = null;
        foreach ($this->getSlots() as $slot) {
            if ($end === null || $slot->getEndsAt() > $end) {
                $end = $slot->getEndsAt();
            }
        }

        return $end;
    }

    /**
     * "18:00-20:00" : de la première heure à la dernière, tous créneaux confondus
     */
    public function getTimeRange(): ?string
    {
        $start = $this->startsAt();

        return $start === null ? null : $start->format('H:i') . '-' . $this->endsAt()->format('H:i');
    }

    /**
     * Terrains occupés, sans doublon
     *
     * @return Court[]
     */
    public function getCourts(): array
    {
        $courts = [];
        foreach ($this->getSlots() as $slot) {
            $courts[$slot->getCourt()->getId()] = $slot->getCourt();
        }

        return array_values($courts);
    }

    /**
     * "BG 1 et BG 2"
     */
    public function getCourtsLabel(): string
    {
        return implode(' et ', array_map(fn (Court $court) => (string) $court, $this->getCourts()));
    }

    /**
     * "Samedi 14/11/26 • 18:00-20:00 • BG 1 et BG 2" (même forme que le créneau d'un match)
     */
    public function getScheduleLabel(): ?string
    {
        $date = $this->getDate();

        return $date === null ? null : $date . ' • ' . $this->getTimeRange() . ' • ' . $this->getCourtsLabel();
    }

    public function isUpcoming(): bool
    {
        return (bool) $this->getDate()?->isFuture();
    }

    public function isPast(): bool
    {
        return (bool) $this->getDate()?->isPast();
    }

    /**
     * Joueurs des poules liées, sans doublon (un joueur peut être dans deux poules), par nom
     *
     * @return Player[]
     */
    public function getPlayers(): array
    {
        $players = [];
        foreach ($this->groups as $group) {
            foreach ($group->getPlayers() as $player) {
                $players[$player->getId()] = $player;
            }
        }

        usort($players, fn (Player $a, Player $b) => [$a->getLastname(), $a->getFirstname()] <=> [$b->getLastname(), $b->getFirstname()]);

        return $players;
    }

    public function concerns(Player $player): bool
    {
        foreach ($this->groups as $group) {
            if ($group->getPlayers()->contains($player)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, PracticeParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(PracticeParticipant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setPractice($this);
        }

        return $this;
    }

    public function getParticipant(Player $player): ?PracticeParticipant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getPlayer() === $player) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * Réponse d'un joueur : true (j'y serai), false (je ne viens pas), null (pas répondu)
     */
    public function getAnswerOf(Player $player): ?bool
    {
        return $this->getParticipant($player)?->isComing();
    }

    /**
     * Joueurs qui ont dit venir, par nom
     *
     * @return Player[]
     */
    public function getComingPlayers(): array
    {
        return $this->playersAnswering(true);
    }

    /**
     * @return Player[]
     */
    public function getNotComingPlayers(): array
    {
        return $this->playersAnswering(false);
    }

    /**
     * @return Player[]
     */
    private function playersAnswering(bool $coming): array
    {
        $players = [];
        foreach ($this->participants as $participant) {
            if ($participant->isComing() === $coming) {
                $players[] = $participant->getPlayer();
            }
        }

        usort($players, fn (Player $a, Player $b) => [$a->getLastname(), $a->getFirstname()] <=> [$b->getLastname(), $b->getFirstname()]);

        return $players;
    }

    /**
     * Joueurs concernés qui ont été prévenus par email d'un créneau à venir
     */
    public function countNotifiedPlayers(): int
    {
        $count = 0;
        foreach ($this->participants as $participant) {
            if ($participant->isEmailSent()) {
                $count++;
            }
        }

        return $count;
    }
}
