<?php

namespace App\Entity;

use App\Repository\ParticipantConfirmationInfoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipantConfirmationInfoRepository::class)]
class ParticipantConfirmationInfo
{
    public const PENDING_NEW = 'new';
    public const PENDING_CHANGED = 'changed';
    public const PENDING_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'confirmationInfo')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MatchParticipant $participant = null;

    #[ORM\Column]
    private bool $isEmailSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    #[ORM\Column]
    private bool $isConfirmedByAdmin = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedByAdminAt = null;

    #[ORM\ManyToOne]
    private ?User $admin = null;

    #[ORM\Column]
    private bool $isConfirmedByPlayer = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedByPlayerAt = null;

    /**
     * Créneau communiqué au joueur lors du dernier email (copie, pas de relation : le créneau peut disparaître).
     * null = le joueur n'a pas de créneau en tête pour ce match (jamais notifié, ou notifié de l'annulation).
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedOn = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $notifiedTimeRange = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $notifiedCourt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $remindedAt = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(): ?MatchParticipant
    {
        return $this->participant;
    }

    public function setParticipant(MatchParticipant $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function isEmailSent(): bool
    {
        return $this->isEmailSent;
    }

    public function setIsEmailSent(bool $isEmailSent): static
    {
        $this->isEmailSent = $isEmailSent;

        return $this;
    }

    public function getEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->emailSentAt;
    }

    public function setEmailSentAt(?\DateTimeImmutable $emailSentAt): static
    {
        $this->emailSentAt = $emailSentAt;

        return $this;
    }

    public function isConfirmedByAdmin(): bool
    {
        return $this->isConfirmedByAdmin;
    }

    public function setIsConfirmedByAdmin(bool $isConfirmedByAdmin): static
    {
        $this->isConfirmedByAdmin = $isConfirmedByAdmin;

        return $this;
    }

    public function getConfirmedByAdminAt(): ?\DateTimeImmutable
    {
        return $this->confirmedByAdminAt;
    }

    public function setConfirmedByAdminAt(?\DateTimeImmutable $confirmedByAdminAt): static
    {
        $this->confirmedByAdminAt = $confirmedByAdminAt;

        return $this;
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(?User $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    public function isConfirmedByPlayer(): bool
    {
        return $this->isConfirmedByPlayer;
    }

    public function setIsConfirmedByPlayer(bool $isConfirmedByPlayer): static
    {
        $this->isConfirmedByPlayer = $isConfirmedByPlayer;

        return $this;
    }

    public function getConfirmedByPlayerAt(): ?\DateTimeImmutable
    {
        return $this->confirmedByPlayerAt;
    }

    public function setConfirmedByPlayerAt(?\DateTimeImmutable $confirmedByPlayerAt): static
    {
        $this->confirmedByPlayerAt = $confirmedByPlayerAt;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmedByAdmin || $this->isConfirmedByPlayer;
    }

    public function getNotifiedOn(): ?\DateTimeImmutable
    {
        return $this->notifiedOn;
    }

    public function getNotifiedTimeRange(): ?string
    {
        return $this->notifiedTimeRange;
    }

    public function getNotifiedCourt(): ?string
    {
        return $this->notifiedCourt;
    }

    public function getRemindedAt(): ?\DateTimeImmutable
    {
        return $this->remindedAt;
    }

    public function setRemindedAt(?\DateTimeImmutable $remindedAt): static
    {
        $this->remindedAt = $remindedAt;

        return $this;
    }

    /**
     * Mémorise le créneau tel qu'il vient d'être communiqué au joueur (null : annulation communiquée)
     */
    public function snapshotSlot(?Slot $slot): static
    {
        $this->notifiedOn = $slot ? \DateTimeImmutable::createFromInterface($slot->getDate()->getDate()) : null;
        $this->notifiedTimeRange = $slot?->getTimeRange();
        $this->notifiedCourt = $slot ? (string) $slot->getCourt() : null;

        return $this;
    }

    /**
     * "Samedi 14/11/26 • 16:00-17:00 • BG 1" tel que communiqué au joueur
     */
    public function getNotifiedLabel(): ?string
    {
        if ($this->notifiedOn === null) {
            return null;
        }

        return Date::formatDay($this->notifiedOn) . ' • ' . $this->notifiedTimeRange . ' • ' . $this->notifiedCourt;
    }

    /**
     * Le créneau actuel diffère-t-il de celui communiqué (date ou heure ; le terrain seul ne compte pas) ?
     */
    public function isSlotChanged(): bool
    {
        $slot = $this->participant?->getMatch()?->getSlot();

        if ($slot === null || $this->notifiedOn === null) {
            return false;
        }

        return $this->notifiedOn->format('Y-m-d') !== $slot->getDate()->getDate()->format('Y-m-d')
            || $this->notifiedTimeRange !== $slot->getTimeRange();
    }

    /**
     * Ce que le joueur doit encore apprendre par email :
     *  - 'new'       : match programmé, jamais communiqué (ou communiqué comme annulé)
     *  - 'changed'   : match programmé à une autre date/heure que celle communiquée
     *  - 'cancelled' : match plus programmé alors qu'un créneau à venir avait été communiqué
     *  - null        : rien à communiquer
     */
    public function getPendingNotification(): ?string
    {
        $match = $this->participant?->getMatch();

        if ($match === null) {
            return null;
        }

        if ($match->isScheduled()) {
            if (!$match->isUpcoming()) {
                return null;
            }
            if (!$this->isEmailSent || $this->notifiedOn === null) {
                return self::PENDING_NEW;
            }

            return $this->isSlotChanged() ? self::PENDING_CHANGED : null;
        }

        if ($this->isEmailSent && $this->notifiedOn !== null && $this->notifiedOn >= new \DateTimeImmutable('today')) {
            return self::PENDING_CANCELLED;
        }

        return null;
    }

    /**
     * Le créneau change : les confirmations ne valent plus
     */
    public function resetConfirmations(): static
    {
        $this->isConfirmedByAdmin = false;
        $this->confirmedByAdminAt = null;
        $this->admin = null;
        $this->isConfirmedByPlayer = false;
        $this->confirmedByPlayerAt = null;
        $this->remindedAt = null;

        return $this;
    }

    /**
     * Pastille sur le planning : default (jamais notifié) / notified / changed (à re-notifier) / confirmed
     *
     * Confirmé avant tout email (le joueur a vu l'horaire sur le site) : confirmed, même si l'email reste à envoyer
     */
    public function getStatus(): string
    {
        $pending = $this->getPendingNotification();

        return match (true) {
            $pending === self::PENDING_CHANGED => 'changed',
            ($pending === null || $pending === self::PENDING_NEW) && $this->isConfirmed() => 'confirmed',
            $pending === null && $this->isEmailSent => 'notified',
            default => 'default',
        };
    }
}
