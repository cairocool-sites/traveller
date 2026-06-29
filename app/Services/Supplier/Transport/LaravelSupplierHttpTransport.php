<?php

namespace App\Services\Supplier\Transport;

use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\Exceptions\UnavailableSupplierException;
use Illuminate\Support\Facades\Http;

class LaravelSupplierHttpTransport implements SupplierHttpTransport
{
    public function request(Supplier $supplier, SupplierOperation $operation, string $method, string $path, array $payload = [], array $headers = []): array
    {
        if ($supplier->base_url === null) {
            throw new UnavailableSupplierException('Supplier base URL is not configured.');
        }

        if (! $operation->isAutomaticallyRetryable() && $supplier->max_retries > 0) {
            $retries = 0;
        } else {
            $retries = $supplier->max_retries;
        }

        $response = Http::baseUrl($supplier->base_url)
            ->timeout($supplier->timeout_seconds)
            ->connectTimeout($supplier->connect_timeout_seconds)
            ->withHeaders($headers)
            ->retry($retries, $supplier->retry_delay_milliseconds)
            ->send($method, $path, ['json' => $payload]);

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->json() ?? ['raw' => $response->body()],
        ];
    }
}
