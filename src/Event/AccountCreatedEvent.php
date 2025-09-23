<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class AccountCreatedEvent extends Event
{
    public const NAME = self::class;

    public function __construct(
        private User $user,
        private ?string $ip,
        private string $password,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
