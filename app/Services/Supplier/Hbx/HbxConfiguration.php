<?php

namespace App\Services\Supplier\Hbx;

use App\Models\Supplier;
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
        return (int) config('services.hbx.timeout', 45);
    }

    public function connectTimeoutSeconds(): int
    {
        return (int) config('services.hbx.connect_timeout', 15);
    }

    public function credentials(?string $correlationId = null, ?Supplier $supplier = null): HbxCredentials
    {
        $supplierCredentials = $this->supplierCredentials($supplier);
        $key = $supplierCredentials['api_key'] ?? config('services.hbx.api_key');
        $secret = $supplierCredentials['api_secret'] ?? config('services.hbx.api_secret');

        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            throw new SupplierAuthenticationException('HBX sandbox credentials are not configured.', $correlationId);
        }

        return new HbxCredentials($key, $secret);
    }

    public function hasCredentials(?Supplier $supplier = null): bool
    {
        try {
            $this->credentials(supplier: $supplier);

            return true;
        } catch (SupplierAuthenticationException) {
            return false;
        }
    }

    private function supplierCredentials(?Supplier $supplier): array
    {
        if (! $supplier) {
            return [];
        }

        return $supplier->credentials()
            ->whereIn('credential_key', ['api_key', 'api_secret'])
            ->pluck('encrypted_value', 'credential_key')
            ->all();
    }
}
