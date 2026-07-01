<?php

namespace App\Services\Supplier\Tbo;

final readonly class TboCredentials
{
    public function __construct(
        public string $username,
        public string $password,
    ) {}

    public function configured(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }
}
