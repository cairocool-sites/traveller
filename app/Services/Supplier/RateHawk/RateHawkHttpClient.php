<?php

namespace App\Services\Supplier\RateHawk;

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

class RateHawkHttpClient
{
    public function __construct(
        private readonly RateHawkConfiguration $config,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly SupplierOperationLogger $logger,
    ) {}

    public function post(Supplier $supplier, SupplierOperation $operation, string $endpointKey, array $payload = [], ?string $correlationId = null, bool $allowRetry = true): array
    {
        $correlationId = $this->correlationIds->make($correlationId);
        $credentials = $this->config->credentials($supplier);

        if (! $credentials->configured()) {
            throw new SupplierAuthenticationException('RateHawk credentials are not configured.', $correlationId);
        }

        $path = $this->config->endpoint($endpointKey);
        $started = microtime(true);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
        ];
        $retries = $allowRetry && $operation->isAutomaticallyRetryable() ? max(0, (int) $supplier->max_retries) : 0;

        try {
            $response = Http::baseUrl($this->config->baseUrl($supplier))
                ->timeout($this->config->timeoutSeconds($supplier))
                ->connectTimeout($this->config->connectTimeoutSeconds($supplier))
                ->withBasicAuth($credentials->keyId, $credentials->apiKey)
                ->withHeaders($headers)
                ->retry($retries, (int) $supplier->retry_delay_milliseconds)
                ->post($path, $payload);

            $body = $response->json();

            if (! is_array($body)) {
                $this->log($supplier, $operation, $correlationId, $path, $headers, $payload, $response->status(), $response->headers(), ['message' => 'Malformed JSON response.'], false, $started, SupplierErrorType::InvalidResponse);

                throw new InvalidSupplierResponseException('RateHawk returned a malformed JSON response.', $correlationId);
            }

            $successful = $response->successful() && ! $this->hasApplicationError($body);
            $this->log($supplier, $operation, $correlationId, $path, $headers, $payload, $response->status(), $response->headers(), $body, $successful, $started);

            if ($response->status() === 401 || $response->status() === 403 || $this->hasAuthenticationError($body)) {
                throw new SupplierAuthenticationException('RateHawk authentication failed.', $correlationId);
            }

            if ($response->status() === 429) {
                throw new SupplierRateLimitException('RateHawk rate limit exceeded.', $correlationId);
            }

            if ($response->serverError()) {
                throw new UnavailableSupplierException('RateHawk service is unavailable.', $correlationId);
            }

            if ($this->hasApplicationError($body)) {
                throw new InvalidSupplierResponseException('RateHawk returned an application error.', $correlationId);
            }

            return ['status' => $response->status(), 'body' => $body, 'correlation_id' => $correlationId];
        } catch (ConnectionException|RequestException $exception) {
            $this->log($supplier, $operation, $correlationId, $path, $headers, $payload, null, null, ['message' => class_basename($exception)], false, $started, SupplierErrorType::Timeout);

            throw new SupplierTimeoutException('RateHawk request timed out or could not connect.', $correlationId);
        }
    }

    private function hasApplicationError(array $body): bool
    {
        return isset($body['error']) && filled($body['error']);
    }

    private function hasAuthenticationError(array $body): bool
    {
        $error = strtolower((string) (data_get($body, 'error') ?? data_get($body, 'debug.validation_error') ?? ''));

        return str_contains($error, 'auth')
            || str_contains($error, 'credential')
            || str_contains($error, 'api key')
            || str_contains($error, 'forbidden')
            || str_contains($error, 'unauthorized');
    }

    private function log(Supplier $supplier, SupplierOperation $operation, string $correlationId, string $path, array $headers, array $requestPayload, ?int $status, ?array $responseHeaders, array $responsePayload, bool $successful, float $started, ?SupplierErrorType $errorType = null): void
    {
        $this->logger->log($supplier, $operation, [
            'correlation_id' => $correlationId,
            'request_method' => 'POST',
            'request_url' => $path,
            'request_headers' => $headers,
            'request_payload' => $requestPayload,
            'response_status' => $status,
            'response_headers' => $responseHeaders,
            'response_payload' => $responsePayload,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'successful' => $successful,
            'error_type' => $successful ? null : ($errorType ?? SupplierErrorType::InvalidResponse),
        ]);
    }
}
