<?php

namespace App\Services\Supplier\Tbo;

use App\Models\Supplier;

class TboConfiguration
{
    public function baseUrl(Supplier $supplier): string
    {
        return rtrim((string) ($supplier->base_url ?: config('services.tbo.base_url')), '/');
    }

    public function timeoutSeconds(Supplier $supplier): int
    {
        return max(15, (int) ($supplier->timeout_seconds ?: config('services.tbo.timeout', 45)));
    }

    public function connectTimeoutSeconds(Supplier $supplier): int
    {
        return max(5, (int) ($supplier->connect_timeout_seconds ?: config('services.tbo.connect_timeout', 15)));
    }

    public function credentials(Supplier $supplier): TboCredentials
    {
        $credentials = $supplier->credentials()
            ->whereIn('credential_key', ['username', 'password'])
            ->get()
            ->keyBy('credential_key');

        return new TboCredentials(
            username: (string) ($credentials->get('username')?->encrypted_value ?: config('services.tbo.username', '')),
            password: (string) ($credentials->get('password')?->encrypted_value ?: config('services.tbo.password', '')),
        );
    }
}
