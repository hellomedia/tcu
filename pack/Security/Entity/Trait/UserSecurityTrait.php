<?php

namespace Pack\Security\Entity\Trait;

use App\Entity\User;
use Pack\Security\Doctrine\Types\CiText;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * - Implements UserInterface: email, roles
 * - Implements PasswordAuthenticatedUserInterface: password
 * - Email verification: verified
 * - Implements EquatableInterface: isEqualTo()
 * - Security Lifecycle: enabled, closed, hellbanned, locked
 * - Security flags: demarchage, scam
 * - Monitoring: watchlist, whitelist, adminReviewed
 * - Kotcop: kotcopScore, kotcopUpdatedAt
 * 
 * ATTN:
 * 1) Don't forget to add unique entity mapping constraint + validation constraint in the User entity
 * 2) Also worth considering adding Pack\Security\Entity to the service exclusion list in services.yaml
 * Traits are probably not added as services, but it is probably a good idea to exclude this Entity directory.
 */
trait UserSecurityTrait
{
    public const DEFAULT_ROLE = 'ROLE_USER';

    /**
     * ===========================
     *  IMPLEMENTS USER INTERFACE
     * ===========================
     * Don't forget to add unique entity mapping constraint + validation constraint in the User entity
     */
    #[ORM\Column(type: CiText::NAME)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * ==================================================
     *  IMPLEMENTS PASSWORD AUTHENTICATED USER INTERFACE
     * ==================================================
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * ======================
     *   EMAIL VERIFICATION
     * ======================
     */
    #[ORM\Column]
    private bool $verified = false;


    /**
     * ======================
     *   SECURITY LIFECYCLE
     * ======================
     */
    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column]
    private bool $closed = false;

    #[ORM\Column]
    private bool $locked = false;

    #[ORM\Column]
    private bool $hellbanned = false;

    /**
     * ===================
     *   SECURITY FLAGS
     * ===================
     */
    #[ORM\Column(nullable: true)]
    private ?bool $scam = null;

    #[ORM\Column(nullable: true)]
    private ?bool $demarchage = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $safe = false;

    /**
     * ===================
     *     MONITORING
     * ===================
     */
    #[ORM\Column]
    private bool $watchlist = false;

    #[ORM\Column]
    private bool $whitelist = false;

    #[ORM\Column]
    private bool $adminReviewed = false;

    /**
     * =================
     *      KOTCOP
     * =================
     */
    #[ORM\Column(nullable: true)]
    private ?float $kotcopScore = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $kotcopUpdatedAt = null;

    /**
     * ===========================
     *  IMPLEMENTS USER INTERFACE
     * ===========================
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantee every user at least has ROLE_USER
        $roles[] = self::DEFAULT_ROLE;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): static
    {
        $role = strtoupper((string) $role);

        if ($role === self::DEFAULT_ROLE) {
            return $this;
        }

        if (!in_array($role, $this->roles ?? [], true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole($role): static
    {
        if (false !== $key = array_search(strtoupper((string) $role), $this->roles ?? [], true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    public function setRolesAfterRegistration(): void
    {
        $this->addRole('ROLE_SEARCHER');

        $this->addRole('ROLE_POSTER');
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * ==================================================
     *  IMPLEMENTS PASSWORD AUTHENTICATED USER INTERFACE
     * ==================================================
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * ====================
     *  EMAIL VERIFICATION
     * ====================
     */
    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function isNotVerified(): bool
    {
        return !$this->verified;
    }

    public function isAccountNonConfirmed(): bool
    {
        return !$this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * ================================
     *  IMPLEMENTS EQUATABLE INTERFACE
     * ================================
     */
    /**
     * Useful for forcing user re-authenthication in some instances
     * ie : roles need to be refreshed, user account closed, etc.
     *
     * The equality comparison should neither be done by referential equality nor by comparing identities (i.e. getId() === getId()).
     * However, you do not need to compare every attribute, but only those that are relevant for assessing whether re-authentication is required.
     * http://api.symfony.com/3.0/Symfony/Component/Security/Core/User/EquatableInterface.html
     * http://symfony.com/doc/current/cookbook/security/custom_provider.html#create-a-user-class
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($this->getEmail() !== $user->getEmail()) {
            return false;
        }

        if ($this->getPassword() !== $user->getPassword()) {
            return false;
        }

        if ($this->getRoles() !== $user->getRoles()) {
            return false;
        }

        if ($this->isEnabled() !== $user->isEnabled()) {
            return false;
        }

        // NB: a hellbanned user does not need to be logged out.
        // The hellbanned flag will do its job without re-authentication.

        return true;
    }

    /**
     * ======================
     *   SECURITY LIFECYCLE
     * ======================
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isNotEnabled(): bool
    {
        return !$this->enabled;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): static
    {
        $this->locked = $locked;

        return $this;
    }

    public function isAccountLocked()
    {
        return $this->locked;
    }

    public function isHellbanned(): bool
    {
        return $this->hellbanned;
    }

    public function setHellbanned(bool $hellbanned): static
    {
        $this->hellbanned = $hellbanned;

        return $this;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isAccountClosed(): bool
    {
        return $this->closed;
    }

    public function isAccountNonClosed(): bool
    {
        return $this->closed == false;
    }

    public function setClosed(bool $closed): static
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Checks if there has been an administrative disabling of the account
     * It is different than a vanilla check on 'enabled' flag, which is FALSE during registration before confirmation
     * NB: Expired accounts are not included ?
     */
    public function isAccountNonDisabledByAdmin(): bool
    {
        if ($this->isAccountClosed()) {
            return false;
        }

        return $this->enabled || $this->verified;
    }

    public function isAccountDisabledByAdmin(): bool
    {
        if ($this->isAccountClosed()) {
            return true;
        }

        return $this->verified && $this->enabled == false;
    }

    /**
     * Does the user have a valid or hellbanned account
     * Checks for everything that can make an account non-valid
     * NB: 'locked' is temporary and does not make an account invalid.
     * NB: A hellbanned account is valid
     * NB: an unconfirmed account is.
     */
    public function isAccountInService(): bool
    {
        if ($this->isAccountClosed()) {
            return false;
        }

        if ($this->isHellbanned()) {
            return true;
        }

        if ($this->isScammer()) {
            return false;
        }

        if ($this->isDemarchage()) {
            return false;
        }

        if ($this->isAccountNonConfirmed()) {
            // technically not in service yet, but shall be soon
            return true;
        }

        return $this->isEnabled();
    }

    public function isAccountNotInService(): bool
    {
        return false == $this->isAccountInService();
    }

    /**
     * ==================
     *   SECURITY FLAGS
     * ==================
     */
    public function isScam(): ?bool
    {
        return $this->scam;
    }

    public function isScammer(): ?bool
    {
        return $this->scam;
    }

    public function setScam(?bool $scam): static
    {
        $this->scam = $scam;

        return $this;
    }

    public function isDemarchage(): ?bool
    {
        return $this->demarchage;
    }

    public function setDemarchage(?bool $demarchage): static
    {
        $this->demarchage = $demarchage;

        return $this;
    }
    
    public function isSafe(): bool
    {
        return $this->safe;
    }
    
    public function setSafe(bool $safe): static
    {
        $this->safe = $safe;
        
        return $this;
    }

    /**
     * ===================
     *     MONITORING
     * ===================
     */
    public function isWatchlist(): ?bool
    {
        return $this->watchlist;
    }

    public function isWatchlisted(): ?bool
    {
        return $this->watchlist;
    }

    public function setWatchlist(?bool $watchlist): static
    {
        $this->watchlist = $watchlist;

        return $this;
    }

    public function isWhitelist(): ?bool
    {
        return $this->whitelist;
    }

    public function isWhitelisted(): ?bool
    {
        return $this->whitelist;
    }

    public function setWhitelist(?bool $whitelist): static
    {
        $this->whitelist = $whitelist;

        return $this;
    }

    public function isAdminReviewed(): ?bool
    {
        return $this->adminReviewed;
    }

    public function setAdminReviewed(?bool $adminReviewed): static
    {
        $this->adminReviewed = $adminReviewed;

        return $this;
    }

    /**
     * =================
     *      KOTCOP
     * =================
     */
    public function getKotcopScore(): ?float
    {
        return $this->kotcopScore;
    }

    public function setKotcopScore(?float $kotcopScore): static
    {
        $this->kotcopScore = $kotcopScore;

        return $this;
    }

    public function getKotcopUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->kotcopUpdatedAt;
    }

    public function setKotcopUpdatedAt(?\DateTimeImmutable $kotcopUpdatedAt): static
    {
        $this->kotcopUpdatedAt = $kotcopUpdatedAt;

        return $this;
    }
}
