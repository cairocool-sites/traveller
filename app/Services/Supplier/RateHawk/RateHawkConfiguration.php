<?php

namespace App\Services\Supplier\RateHawk;

use App\Models\Supplier;

class RateHawkConfiguration
{
    public function baseUrl(Supplier $supplier): string
    {
        return rtrim((string) ($supplier->base_url ?: config('services.ratehawk.base_url')), '/');
    }

    public function timeoutSeconds(Supplier $supplier): int
    {
        return max(15, (int) ($supplier->timeout_seconds ?: config('services.ratehawk.timeout', 45)));
    }

    public function connectTimeoutSeconds(Supplier $supplier): int
    {
        return max(5, (int) ($supplier->connect_timeout_seconds ?: config('services.ratehawk.connect_timeout', 15)));
    }

    public function credentials(Supplier $supplier): RateHawkCredentials
    {
        $credentials = $supplier->credentials()
            ->whereIn('credential_key', ['key_id', 'api_key'])
            ->get()
            ->keyBy('credential_key');

        return new RateHawkCredentials(
            keyId: (string) ($credentials->get('key_id')?->encrypted_value ?: config('services.ratehawk.key_id', '')),
            apiKey: (string) ($credentials->get('api_key')?->encrypted_value ?: config('services.ratehawk.api_key', '')),
        );
    }

    public function endpoint(string $key): string
    {
        return (string) config("services.ratehawk.endpoints.{$key}");
    }

    public function endpoints(): array
    {
        return config('services.ratehawk.endpoints', []);
    }
}
