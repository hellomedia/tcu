<?php

namespace App\Entity;

use App\Enum\Side;
use App\Repository\MatchParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchParticipantRepository::class)]
class MatchParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InterfacMatch $match = null;

    #[ORM\ManyToOne(inversedBy: 'matchParticipations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column(enumType: Side::class)]
    private ?Side $side = null;

    #[ORM\OneToOne(mappedBy: 'participant', cascade: ['persist', 'remove'], fetch: 'EAGER')]
    private ?ParticipantConfirmationInfo $confirmationInfo = null;

    public function __toString()
    {
        return $this->player;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): ?InterfacMatch
    {
        return $this->match;
    }

    public function setMatch(?InterfacMatch $match): static
    {
        $this->match = $match;

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

    public function getUser(): ?User
    {
        return $this->player->getUser();
    }

    public function getSide(): ?Side
    {
        return $this->side;
    }

    public function setSide(Side $side): static
    {
        $this->side = $side;

        return $this;
    }

    public function getConfirmationInfo(): ?ParticipantConfirmationInfo
    {
        return $this->confirmationInfo;
    }

    public function setConfirmationInfo(ParticipantConfirmationInfo $confirmationInfo): static
    {
        // set the owning side of the relation if necessary
        if ($confirmationInfo->getParticipant() !== $this) {
            $confirmationInfo->setParticipant($this);
        }

        $this->confirmationInfo = $confirmationInfo;

        return $this;
    }

    public function isConfirmed(): bool
    {
        if ($this->getConfirmationInfo()) {
            return $this->confirmationInfo->isConfirmed();
        }

        return false;
    }

    public function isNotified(): bool
    {
        if ($this->getConfirmationInfo()) {
            return $this->confirmationInfo->isEmailSent();
        }

        return false;
    }

    public function getConfirmationStatus(): string
    {
        if ($this->getConfirmationInfo()) {
            return $this->confirmationInfo->getStatus();
        }

        return 'default'; 
    }
}
