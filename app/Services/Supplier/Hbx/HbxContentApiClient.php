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

class HbxContentApiClient
{
    public const COUNTRIES_PATH = '/hotel-content-api/1.0/locations/countries';

    public const DESTINATIONS_PATH = '/hotel-content-api/1.0/locations/destinations';

    public const HOTELS_PATH = '/hotel-content-api/1.0/hotels';

    public const RESOURCE_PATHS = [
        'zones' => '/hotel-content-api/1.0/locations/zones',
        'rooms' => '/hotel-content-api/1.0/types/rooms',
        'boards' => '/hotel-content-api/1.0/types/boards',
        'accommodations' => '/hotel-content-api/1.0/types/accommodations',
        'categories' => '/hotel-content-api/1.0/types/categories',
        'category_groups' => '/hotel-content-api/1.0/types/categorygroups',
        'chains' => '/hotel-content-api/1.0/types/chains',
        'facilities' => '/hotel-content-api/1.0/types/facilities',
        'facility_groups' => '/hotel-content-api/1.0/types/facilitygroups',
        'issues' => '/hotel-content-api/1.0/types/issues',
        'languages' => '/hotel-content-api/1.0/types/languages',
        'promotions' => '/hotel-content-api/1.0/types/promotions',
        'segments' => '/hotel-content-api/1.0/types/segments',
        'image_types' => '/hotel-content-api/1.0/types/imagetypes',
        'currencies' => '/hotel-content-api/1.0/types/currencies',
        'terminals' => '/hotel-content-api/1.0/types/terminals',
        'rate_comments' => '/hotel-content-api/1.0/types/ratecomments',
    ];

    public function __construct(
        private readonly HbxConfiguration $config,
        private readonly HbxSignatureService $signatures,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly SupplierOperationLogger $logger,
    ) {}

    public function countries(Supplier $supplier, array $query = [], ?string $correlationId = null): array
    {
        return $this->get($supplier, self::COUNTRIES_PATH, $query, $correlationId);
    }

    public function destinations(Supplier $supplier, array $query = [], ?string $correlationId = null): array
    {
        return $this->get($supplier, self::DESTINATIONS_PATH, $query, $correlationId);
    }

    public function hotels(Supplier $supplier, array $query = [], ?string $correlationId = null): array
    {
        return $this->get($supplier, self::HOTELS_PATH, $query, $correlationId);
    }

    public function hotelDetails(Supplier $supplier, string $hotelCode, array $query = [], ?string $correlationId = null): array
    {
        return $this->get($supplier, self::HOTELS_PATH.'/'.rawurlencode($hotelCode).'/details', $query, $correlationId);
    }

    public function resource(Supplier $supplier, string $resource, array $query = [], ?string $correlationId = null): array
    {
        if (! isset(self::RESOURCE_PATHS[$resource])) {
            throw new \InvalidArgumentException("Unsupported HBX Content API resource [{$resource}].");
        }

        return $this->get($supplier, self::RESOURCE_PATHS[$resource], $query, $correlationId);
    }

    public function fullUrl(Supplier $supplier, string $path): string
    {
        return rtrim((string) ($supplier->base_url ?: $this->config->baseUrl()), '/').'/'.ltrim($path, '/');
    }

    private function get(Supplier $supplier, string $path, array $query, ?string $correlationId): array
    {
        $correlationId = $this->correlationIds->make($correlationId);
        $credentials = $this->config->credentials($correlationId);
        $headers = [
            'Api-key' => $credentials->apiKey,
            'X-Signature' => $this->signatures->signature($credentials->apiKey, $credentials->apiSecret),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
        ];
        $started = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) ($supplier->base_url ?: $this->config->baseUrl()), '/'))
                ->timeout(max(45, (int) ($supplier->timeout_seconds ?: $this->config->timeoutSeconds())))
                ->connectTimeout(max(15, (int) ($supplier->connect_timeout_seconds ?: $this->config->connectTimeoutSeconds())))
                ->withHeaders($headers)
                ->get($path, array_filter($query, fn (mixed $value): bool => $value !== null && $value !== ''));

            $body = $response->json();

            if (! is_array($body)) {
                $this->log($supplier, $correlationId, $path, $query, $headers, $response->status(), $response->headers(), ['message' => 'Malformed JSON response.'], false, $started, SupplierErrorType::InvalidResponse);

                throw new InvalidSupplierResponseException('HBX Content API returned malformed JSON.', $correlationId);
            }

            $this->log($supplier, $correlationId, $path, $query, $headers, $response->status(), $response->headers(), $body, $response->successful(), $started);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new SupplierAuthenticationException('HBX Content API authentication failed.', $correlationId);
            }

            if ($response->status() === 429) {
                throw new SupplierRateLimitException('HBX Content API rate limit exceeded.', $correlationId);
            }

            if ($response->clientError()) {
                throw new InvalidSupplierResponseException('HBX Content API rejected the request schema, endpoint, or version.', $correlationId);
            }

            if ($response->serverError()) {
                throw new UnavailableSupplierException('HBX Content API is unavailable.', $correlationId);
            }

            return ['status' => $response->status(), 'body' => $body, 'correlation_id' => $correlationId];
        } catch (ConnectionException|RequestException $exception) {
            $this->log($supplier, $correlationId, $path, $query, $headers, null, null, ['message' => class_basename($exception)], false, $started, SupplierErrorType::Timeout);

            throw new SupplierTimeoutException('HBX Content API request timed out or could not connect.', $correlationId);
        }
    }

    private function log(Supplier $supplier, string $correlationId, string $path, array $query, array $headers, ?int $status, ?array $responseHeaders, array $responsePayload, bool $successful, float $started, ?SupplierErrorType $errorType = null): void
    {
        $this->logger->log($supplier, SupplierOperation::HotelDetails, [
            'correlation_id' => $correlationId,
            'request_method' => 'GET',
            'request_url' => $path,
            'request_headers' => $headers,
            'request_payload' => $query,
            'response_status' => $status,
            'response_headers' => $responseHeaders,
            'response_payload' => $responsePayload,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'successful' => $successful,
            'error_type' => $successful ? null : ($errorType ?? SupplierErrorType::InvalidResponse),
        ]);
    }
}
