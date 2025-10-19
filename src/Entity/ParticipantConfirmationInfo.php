<?php

namespace App\Entity;

use App\Repository\ParticipantConfirmationInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipantConfirmationInfoRepository::class)]
class ParticipantConfirmationInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'confirmationInfo', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?MatchParticipant $participant = null;

    #[ORM\Column]
    private ?bool $isEmailSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    #[ORM\Column]
    private ?bool $isConfirmedByAdmin = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedByAdminAt = null;

    #[ORM\ManyToOne]
    private ?User $admin = null;

    #[ORM\Column]
    private ?bool $isConfirmedByPlayer = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedByPlayerAt = null;


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

    public function isEmailSent(): ?bool
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

    public function isConfirmedByAdmin(): ?bool
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

    public function isConfirmedByPlayer(): ?bool
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
}
