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

    public function diagnostics(Supplier $supplier, string $method, string $path): HbxRequestDiagnostics
    {
        $baseUrl = $this->baseUrl($supplier);
        $fullUrl = $this->fullUrl($supplier, $path);

        return new HbxRequestDiagnostics(
            targetHost: parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
            targetPath: parse_url($fullUrl, PHP_URL_PATH) ?: $path,
            method: strtoupper($method),
            connectTimeoutSeconds: $this->connectTimeoutSeconds($supplier),
            timeoutSeconds: $this->timeoutSeconds($supplier),
            proxyConfigured: $this->proxyConfigured(),
            responseReceived: false,
        );
    }

    public function fullUrl(Supplier $supplier, string $path): string
    {
        return $this->baseUrl($supplier).'/'.ltrim($path, '/');
    }

    public function request(Supplier $supplier, SupplierOperation $operation, string $method, string $path, array $payload = [], ?string $correlationId = null, bool $allowRetry = true): array
    {
        $correlationId = $this->correlationIds->make($correlationId);
        $credentials = $this->config->credentials($correlationId);
        $started = microtime(true);
        $headers = [
            'Api-key' => $credentials->apiKey,
            'X-Signature' => $this->signatures->signature($credentials->apiKey, $credentials->apiSecret),
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
        ];
        $retries = $allowRetry && $operation->isAutomaticallyRetryable() ? max(0, (int) $supplier->max_retries) : 0;

        try {
            $response = Http::baseUrl($this->baseUrl($supplier))
                ->timeout($this->timeoutSeconds($supplier))
                ->connectTimeout($this->connectTimeoutSeconds($supplier))
                ->withHeaders($headers)
                ->retry($retries, (int) $supplier->retry_delay_milliseconds)
                ->send($method, $path, $payload === [] ? [] : ['json' => $payload]);

            $body = $response->json();

            if (! is_array($body)) {
                $this->log($supplier, $operation, $correlationId, $method, $path, $headers, $payload, $response->status(), $response->headers(), ['message' => 'Malformed JSON response.'], false, $started, SupplierErrorType::InvalidResponse);

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

    private function baseUrl(Supplier $supplier): string
    {
        return rtrim((string) ($supplier->base_url ?: $this->config->baseUrl()), '/');
    }

    private function timeoutSeconds(Supplier $supplier): int
    {
        return max(45, (int) ($supplier->timeout_seconds ?: $this->config->timeoutSeconds()));
    }

    private function connectTimeoutSeconds(Supplier $supplier): int
    {
        return max(15, (int) ($supplier->connect_timeout_seconds ?: $this->config->connectTimeoutSeconds()));
    }

    private function proxyConfigured(): bool
    {
        return filled(env('HTTP_PROXY')) || filled(env('HTTPS_PROXY')) || filled(env('ALL_PROXY'));
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
