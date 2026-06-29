<?php

namespace App\Services\Supplier\Hbx;

final readonly class HbxCredentials
{
    public function __construct(
        public string $apiKey,
        public string $apiSecret,
    ) {}
}
