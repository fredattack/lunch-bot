<?php

namespace App\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

class Actor implements Authenticatable
{
    public function __construct(
        public readonly string $providerUserId,
        public readonly bool $isAdmin = false
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'provider_user_id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->providerUserId;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
