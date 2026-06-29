<?php

namespace App\Console\Commands;

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\Supplier\Hbx\HbxConfiguration;
use App\Services\Supplier\Hbx\HbxContentApiClient;
use App\Services\Supplier\Hbx\HbxSignatureService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HbxContentDiagnoseHotelsCommand extends Command
{
    private const SUPPLIER_CODE = 'hbx_hotels';

    private const SANDBOX_BASE_URL = 'https://api.test.hotelbeds.com';

    protected $signature = 'hbx:content:diagnose-hotels
        {--from=1 : Official Content API initial record}
        {--to=10 : Official Content API final record}
        {--language=ENG : Official HBX language code}
        {--fields=all : Official fields parameter}
        {--country= : Optional official countryCode filter}
        {--destination= : Optional official destinationCode filter}
        {--codes= : Optional official comma-separated hotel codes filter}
        {--details : Diagnose the official /hotels/{hotelCodes}/details endpoint for --codes}';

    protected $description = 'Safely diagnose the official HBX Content API hotels endpoint without storing raw responses.';

    public function handle(HbxConfiguration $config, HbxSignatureService $signatures): int
    {
        $supplier = $this->supplier();
        if (! $supplier) {
            $this->error('hbx_hotels supplier is missing, inactive, or not configured for the Sandbox endpoint.');

            return self::FAILURE;
        }

        if ($this->option('details') && trim((string) $this->option('codes')) === '') {
            $this->error('Use --codes with --details.');

            return self::FAILURE;
        }

        $credentials = $config->credentials();
        $baseUrl = rtrim((string) ($supplier->base_url ?: $config->baseUrl()), '/');
        $path = $this->path();
        $query = $this->query();
        $headers = [
            'Api-key' => $credentials->apiKey,
            'X-Signature' => $signatures->signature($credentials->apiKey, $credentials->apiSecret),
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
        ];
        $started = microtime(true);

        $this->line('Resolved base URL: '.$baseUrl);
        $this->line('Endpoint path: '.$path);
        $this->line('API version: 1.0');
        $this->line('HTTP method: GET');
        $this->line('Query parameters: '.json_encode($query, JSON_THROW_ON_ERROR));
        $this->line('Api-key header present: yes');
        $this->line('X-Signature header present: yes');
        $this->line('Accept header: application/json');
        $this->line('Accept-Encoding header: gzip');

        try {
            $response = Http::baseUrl($baseUrl)
                ->connectTimeout(max(15, (int) ($supplier->connect_timeout_seconds ?: $config->connectTimeoutSeconds())))
                ->timeout(max(45, (int) ($supplier->timeout_seconds ?: $config->timeoutSeconds())))
                ->withHeaders($headers)
                ->get($path, $query);
        } catch (ConnectionException) {
            $this->line('HTTP status: no response');
            $this->line('Response content type: unavailable');
            $this->line('Response envelope keys: unavailable');
            $this->line('Elapsed ms: '.(int) round((microtime(true) - $started) * 1000));
            $this->line('Classification: connection_or_timeout');

            return self::FAILURE;
        }

        $body = $response->json();
        $safeError = $this->safeError($body);

        $this->line('HTTP status: '.$response->status());
        if ($safeError) {
            $this->line('HBX error: '.$safeError);
        }
        $this->line('Response content type: '.($response->header('Content-Type') ?: 'unknown'));
        $this->line('Response envelope keys: '.$this->envelopeKeys($body));
        $this->line('Elapsed ms: '.(int) round((microtime(true) - $started) * 1000));
        $this->line('Classification: '.$this->classification($response->status(), is_array($body)));

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function supplier(): ?Supplier
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();

        if (! $supplier || $supplier->status !== SupplierStatus::Active) {
            return null;
        }

        $baseUrl = rtrim((string) ($supplier->base_url ?: config('services.hbx.base_url')), '/');

        return $baseUrl === self::SANDBOX_BASE_URL ? $supplier : null;
    }

    private function query(): array
    {
        if ($this->option('details')) {
            return array_filter([
                'language' => strtoupper((string) $this->option('language')),
                'useSecondaryLanguage' => 'false',
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return array_filter([
            'fields' => $this->option('fields') ?: null,
            'language' => strtoupper((string) $this->option('language')),
            'countryCode' => $this->option('country') ? strtoupper((string) $this->option('country')) : null,
            'destinationCode' => $this->option('destination') ? strtoupper((string) $this->option('destination')) : null,
            'codes' => $this->option('codes') ?: null,
            'from' => max(1, (int) $this->option('from')),
            'to' => max(max(1, (int) $this->option('from')), (int) $this->option('to')),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function path(): string
    {
        if (! $this->option('details')) {
            return HbxContentApiClient::HOTELS_PATH;
        }

        $codes = preg_replace('/[^0-9,]+/', '', (string) $this->option('codes'));

        return sprintf(HbxContentApiClient::HOTEL_DETAILS_PATH_TEMPLATE, $codes);
    }

    private function safeError(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $error = $body['error'] ?? null;
        if (is_string($error)) {
            return Str::of($error)->squish()->limit(180, '')->toString();
        }

        if (is_array($error)) {
            $code = isset($error['code']) ? Str::of((string) $error['code'])->squish()->limit(80, '')->toString() : null;
            $message = isset($error['message']) ? Str::of((string) $error['message'])->squish()->limit(180, '')->toString() : null;

            return trim(implode(' ', array_filter([$code, $message])));
        }

        return null;
    }

    private function envelopeKeys(mixed $body): string
    {
        if (! is_array($body)) {
            return 'unavailable';
        }

        return implode(', ', array_slice(array_keys($body), 0, 12));
    }

    private function classification(int $status, bool $jsonBody): string
    {
        if ($status >= 200 && $status < 300 && $jsonBody) {
            return 'success';
        }

        return match (true) {
            $status === 401 => 'authentication',
            $status === 403 => 'authorization_or_quota',
            $status === 406 => 'accept_header',
            $status === 415 => 'content_type',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'supplier_server_error',
            $status >= 400 => 'request_schema_or_endpoint',
            default => 'unexpected_response',
        };
    }
}
