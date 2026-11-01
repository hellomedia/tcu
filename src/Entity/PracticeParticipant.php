<?php

namespace App\Entity;

use App\Repository\PracticeParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un joueur d'une poule liée à un entraînement : sa réponse (j'y serai / je ne viens pas)
 * et ce qui lui a été communiqué par email (comme ParticipantConfirmationInfo pour un match).
 *
 * Créé à la demande : quand le joueur répond ou quand un email lui est envoyé.
 * Les joueurs concernés sont ceux des poules liées (Practice::getPlayers()), pas ces lignes.
 */
#[ORM\Entity(repositoryClass: PracticeParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRACTICE_PLAYER', fields: ['practice', 'player'])]
class PracticeParticipant
{
    public const PENDING_NEW = 'new';
    public const PENDING_CHANGED = 'changed';
    public const PENDING_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Practice $practice = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Player $player = null;

    /**
     * true : j'y serai, false : je ne viens pas, null : pas répondu
     */
    #[ORM\Column(nullable: true)]
    private ?bool $coming = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $answeredAt = null;

    #[ORM\Column]
    private bool $isEmailSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    /**
     * Horaire communiqué au joueur lors du dernier email (copie, pas de relation : les créneaux peuvent changer).
     * null = rien en tête pour cet entraînement (jamais notifié, ou notifié de l'annulation).
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedOn = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $notifiedTimeRange = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $notifiedCourts = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPractice(): ?Practice
    {
        return $this->practice;
    }

    public function setPractice(?Practice $practice): static
    {
        $this->practice = $practice;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function isComing(): ?bool
    {
        return $this->coming;
    }

    public function hasAnswered(): bool
    {
        return $this->coming !== null;
    }

    public function answer(?bool $coming): static
    {
        $this->coming = $coming;
        $this->answeredAt = $coming === null ? null : new \DateTimeImmutable();

        return $this;
    }

    public function getAnsweredAt(): ?\DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function isEmailSent(): bool
    {
        return $this->isEmailSent;
    }

    public function getEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->emailSentAt;
    }

    public function getNotifiedOn(): ?\DateTimeImmutable
    {
        return $this->notifiedOn;
    }

    /**
     * Mémorise l'horaire tel qu'il vient d'être communiqué au joueur (annulation communiquée : rien en tête)
     */
    public function markNotified(\DateTimeImmutable $now): static
    {
        $this->isEmailSent = true;
        $this->emailSentAt = $now;

        $practice = $this->practice;

        if ($practice === null || $practice->isCancelled() || !$practice->hasSchedule()) {
            $this->notifiedOn = null;
            $this->notifiedTimeRange = null;
            $this->notifiedCourts = null;

            return $this;
        }

        $this->notifiedOn = $practice->getDay();
        $this->notifiedTimeRange = $practice->getTimeRange();
        $this->notifiedCourts = mb_substr($practice->getCourtsLabel(), 0, 100);

        return $this;
    }

    /**
     * "Samedi 14/11/26 • 18:00-20:00 • BG 1 et BG 2" tel que communiqué au joueur
     */
    public function getNotifiedLabel(): ?string
    {
        if ($this->notifiedOn === null) {
            return null;
        }

        return Date::formatDay($this->notifiedOn) . ' • ' . $this->notifiedTimeRange . ' • ' . $this->notifiedCourts;
    }

    /**
     * L'horaire actuel diffère-t-il de celui communiqué (date ou heures ; les terrains seuls ne comptent pas) ?
     */
    public function isScheduleChanged(): bool
    {
        $practice = $this->practice;

        if ($practice === null || !$practice->hasSchedule() || $this->notifiedOn === null) {
            return false;
        }

        return $this->notifiedOn->format('Y-m-d') !== $practice->getDay()->format('Y-m-d')
            || $this->notifiedTimeRange !== $practice->getTimeRange();
    }

    /**
     * Ce que le joueur doit encore apprendre par email (voir MatchNotifications) :
     *  - 'new'       : entraînement à venir jamais communiqué (ou communiqué comme annulé)
     *  - 'changed'   : entraînement à venir à une autre date/heure que celle communiquée
     *  - 'cancelled' : entraînement annulé alors qu'un horaire à venir avait été communiqué
     *  - null        : rien à communiquer
     */
    public function getPendingNotification(): ?string
    {
        return self::pendingNotification($this->practice, $this);
    }

    /**
     * Même calcul pour un joueur sans ligne (jamais notifié, jamais répondu)
     */
    public static function pendingNotification(?Practice $practice, ?self $participant): ?string
    {
        if ($practice === null || !$practice->hasSchedule()) {
            return null;
        }

        if (!$practice->isCancelled()) {
            if (!$practice->isUpcoming()) {
                return null;
            }
            if ($participant === null || !$participant->isEmailSent || $participant->notifiedOn === null) {
                return self::PENDING_NEW;
            }

            return $participant->isScheduleChanged() ? self::PENDING_CHANGED : null;
        }

        if ($participant !== null && $participant->isEmailSent && $participant->notifiedOn !== null && $participant->notifiedOn >= new \DateTimeImmutable('today')) {
            return self::PENDING_CANCELLED;
        }

        return null;
    }
}
