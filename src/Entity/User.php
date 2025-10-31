<?php

namespace App\Entity;

use App\Entity\Interface\EntityInterface;
use App\Enum\AccountLanguage;
use App\Repository\UserRepository;
use Pack\Security\Entity\Trait\UserSecurityTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Table(name: 'user_')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[Constraints\UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface, EntityInterface
{
    use UserSecurityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastLogin = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(enumType: AccountLanguage::class, options: ['default' => 'fr'])]
    private ?AccountLanguage $accountLanguage = null;

    #[ORM\OneToOne(inversedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Player $player = null;

    public function __construct()
    {
    }

    public function __toString(): string
    {
        return $this->name ?? $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLastLogin(): ?DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTimeImmutable $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt = null): static
    {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt = null): static
    {
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();

        return $this;
    }

    public function getAccountLanguage(): ?AccountLanguage
    {
        return $this->accountLanguage;
    }

    public function setAccountLanguage(AccountLanguage $accountLanguage): static
    {
        $this->accountLanguage = $accountLanguage;

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

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getMatchs(): ?Collection
    {
        return $this->player?->getMatchs();
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getScheduledMatchs(): ?Collection
    {
        return $this->player?->getScheduledMatchs();
    }

    /**
     * @return Collection<int, InterfacMatch>
     */
    public function getNonScheduledMatchs(): ?Collection
    {
        return $this->player?->getNonScheduledMatchs();
    }


}
