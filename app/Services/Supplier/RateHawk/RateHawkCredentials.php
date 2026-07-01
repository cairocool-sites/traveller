<?php

namespace App\Services\Supplier\RateHawk;

final readonly class RateHawkCredentials
{
    public function __construct(
        public string $keyId,
        public string $apiKey,
    ) {}

    public function configured(): bool
    {
        return $this->keyId !== '' && $this->apiKey !== '';
    }
}
