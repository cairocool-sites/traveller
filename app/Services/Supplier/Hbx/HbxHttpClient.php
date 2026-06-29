<?php

namespace App\Services\Supplier\Hbx;

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Exceptions\InvalidSupplierResponseException;
use App\Services\Supplier\Exceptions\SupplierAuthenticationException;
use App\Services\Supplier\Exceptions\SupplierRateLimitException;
use App\Services\Supplier\Exceptions\SupplierTimeoutException;
use App\Services\Supplier\Exceptions\UnavailableSupplierException;
use App\Services\Supplier\SupplierOperationLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HbxHttpClient
{
    public function __construct(
        private readonly HbxConfiguration $config,
        private readonly HbxSignatureService $signatures,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly SupplierOperationLogger $logger,
    ) {}

    public function request(Supplier $supplier, SupplierOperation $operation, string $method, string $path, array $payload = [], ?string $correlationId = null): array
    {
        $correlationId = $this->correlationIds->make($correlationId);
        $credentials = $this->config->credentials($correlationId);
        $started = microtime(true);
        $headers = [
            'Api-key' => $credentials->apiKey,
            'X-Signature' => $this->signatures->signature($credentials->apiKey, $credentials->apiSecret),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
        ];
        $retries = $operation->isAutomaticallyRetryable() ? max(0, (int) $supplier->max_retries) : 0;

        try {
            $response = Http::baseUrl($supplier->base_url ?: $this->config->baseUrl())
                ->timeout((int) ($supplier->timeout_seconds ?: $this->config->timeoutSeconds()))
                ->connectTimeout((int) ($supplier->connect_timeout_seconds ?: $this->config->connectTimeoutSeconds()))
                ->withHeaders($headers)
                ->retry($retries, (int) $supplier->retry_delay_milliseconds)
                ->send($method, $path, $payload === [] ? [] : ['json' => $payload]);

            $body = $response->json();

            if (! is_array($body)) {
                throw new InvalidSupplierResponseException('HBX returned a malformed JSON response.', $correlationId);
            }

            $this->log($supplier, $operation, $correlationId, $method, $path, $headers, $payload, $response->status(), $response->headers(), $body, $response->successful(), $started);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new SupplierAuthenticationException('HBX authentication failed.', $correlationId);
            }

            if ($response->status() === 429) {
                throw new SupplierRateLimitException('HBX rate limit exceeded.', $correlationId);
            }

            if ($response->serverError()) {
                throw new UnavailableSupplierException('HBX service is unavailable.', $correlationId);
            }

            return ['status' => $response->status(), 'body' => $body, 'correlation_id' => $correlationId];
        } catch (ConnectionException|RequestException $exception) {
            $this->log($supplier, $operation, $correlationId, $method, $path, $headers, $payload, null, null, ['message' => class_basename($exception)], false, $started, SupplierErrorType::Timeout);

            throw new SupplierTimeoutException('HBX request timed out or could not connect.', $correlationId);
        }
    }

    private function log(Supplier $supplier, SupplierOperation $operation, string $correlationId, string $method, string $path, array $headers, array $payload, ?int $status, ?array $responseHeaders, array $responsePayload, bool $successful, float $started, ?SupplierErrorType $errorType = null): void
    {
        $this->logger->log($supplier, $operation, [
            'correlation_id' => $correlationId,
            'request_method' => $method,
            'request_url' => $path,
            'request_headers' => $headers,
            'request_payload' => $payload,
            'response_status' => $status,
            'response_headers' => $responseHeaders,
            'response_payload' => $responsePayload,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'successful' => $successful,
            'error_type' => $successful ? null : ($errorType ?? SupplierErrorType::InvalidResponse),
        ]);
    }
}
