<?php

namespace App\Services\Supplier\Tbo;

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

class TboHttpClient
{
    public function __construct(
        private readonly TboConfiguration $config,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly SupplierOperationLogger $logger,
    ) {}

    public function request(Supplier $supplier, SupplierOperation $operation, string $endpointKey, array $payload = [], ?string $correlationId = null, bool $allowRetry = true): array
    {
        $correlationId = $this->correlationIds->make($correlationId);
        $credentials = $this->config->credentials($supplier);

        if (! $credentials->configured()) {
            throw new SupplierAuthenticationException('TBO credentials are not configured.', $correlationId);
        }

        $path = $this->config->endpoint($endpointKey);
        $started = microtime(true);
        $requestPayload = array_merge([
            'UserName' => $credentials->username,
            'Password' => $credentials->password,
        ], $payload);
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
                ->withHeaders($headers)
                ->retry($retries, (int) $supplier->retry_delay_milliseconds)
                ->post($path, $requestPayload);

            $body = $response->json();

            if (! is_array($body)) {
                $this->log($supplier, $operation, $correlationId, $path, $headers, $requestPayload, $response->status(), $response->headers(), ['message' => 'Malformed JSON response.'], false, $started, SupplierErrorType::InvalidResponse);

                throw new InvalidSupplierResponseException('TBO returned a malformed JSON response.', $correlationId);
            }

            $this->log($supplier, $operation, $correlationId, $path, $headers, $requestPayload, $response->status(), $response->headers(), $body, $response->successful(), $started);

            if ($response->status() === 401 || $response->status() === 403 || $this->hasAuthenticationError($body)) {
                throw new SupplierAuthenticationException('TBO authentication failed.', $correlationId);
            }

            if ($response->status() === 429) {
                throw new SupplierRateLimitException('TBO rate limit exceeded.', $correlationId);
            }

            if ($response->serverError()) {
                throw new UnavailableSupplierException('TBO service is unavailable.', $correlationId);
            }

            return ['status' => $response->status(), 'body' => $body, 'correlation_id' => $correlationId];
        } catch (ConnectionException|RequestException $exception) {
            $this->log($supplier, $operation, $correlationId, $path, $headers, $requestPayload, null, null, ['message' => class_basename($exception)], false, $started, SupplierErrorType::Timeout);

            throw new SupplierTimeoutException('TBO request timed out or could not connect.', $correlationId);
        }
    }

    private function hasAuthenticationError(array $body): bool
    {
        $status = data_get($body, 'Status.Code') ?? data_get($body, 'Status.StatusCode') ?? data_get($body, 'HotelSearchResult.ResponseStatus');
        $message = strtolower((string) (data_get($body, 'Status.Description') ?? data_get($body, 'Error.ErrorMessage') ?? data_get($body, 'HotelSearchResult.Error.ErrorMessage') ?? ''));

        return in_array((string) $status, ['2', '401', '403'], true)
            && (str_contains($message, 'auth') || str_contains($message, 'credential') || str_contains($message, 'password') || str_contains($message, 'token'));
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
