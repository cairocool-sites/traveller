<?php

namespace App\Services\Supplier\Hbx;

use App\Services\Supplier\Exceptions\SupplierAuthenticationException;

class HbxConfiguration
{
    public function enabled(): bool
    {
        return (bool) config('services.hbx.enabled');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.hbx.base_url'), '/');
    }

    public function timeoutSeconds(): int
    {
        return (int) config('services.hbx.timeout', 20);
    }

    public function connectTimeoutSeconds(): int
    {
        return (int) config('services.hbx.connect_timeout', 5);
    }

    public function credentials(?string $correlationId = null): HbxCredentials
    {
        $key = config('services.hbx.api_key');
        $secret = config('services.hbx.api_secret');

        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            throw new SupplierAuthenticationException('HBX sandbox credentials are not configured.', $correlationId);
        }

        return new HbxCredentials($key, $secret);
    }
}
