<?php

use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationSupplierStatus;
use App\Enums\RateRefundability;
use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\City;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use App\Models\SupplierOperationLog;
use App\Services\PublicSearch\HotelSearchService;
use App\Services\Supplier\Contracts\HotelSupplierInterface;
use App\Services\Supplier\Data\CheckRateRequestData;
use App\Services\Supplier\Data\GuestData;
use App\Services\Supplier\Data\HotelSearchRequestData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\Data\SupplierBookingRequestData;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\Exceptions\InvalidSupplierResponseException;
use App\Services\Supplier\Exceptions\SupplierAuthenticationException;
use App\Services\Supplier\Exceptions\SupplierRateLimitException;
use App\Services\Supplier\Exceptions\SupplierTimeoutException;
use App\Services\Supplier\Hbx\HbxHotelSupplier;
use App\Services\Supplier\Hbx\HbxHttpClient;
use App\Services\Supplier\Hbx\HbxSignatureService;
use App\Services\Supplier\SupplierManager;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\AdminFoundationSeeder;
use Database\Seeders\CoreReferenceDataSeeder;
use Database\Seeders\SupplierFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'test-api-key',
        'services.hbx.api_secret' => 'test-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
    ]);

    $this->seed(AdminFoundationSeeder::class);
    $this->seed(CoreReferenceDataSeeder::class);
    $this->seed(SupplierFoundationSeeder::class);

    Supplier::query()->where('code', 'hbx_hotels')->update(['status' => SupplierStatus::Active]);
    hbxSupplierSeedMapping();
});

afterEach(function (): void {
    config([
        'services.hbx.enabled' => false,
        'services.hbx.api_key' => null,
        'services.hbx.api_secret' => null,
    ]);
});

it('generates the official hbx signature deterministically', function () {
    $timestamp = CarbonImmutable::createFromTimestampUTC(1700000000);

    expect(app(HbxSignatureService::class)->signature('key', 'secret', $timestamp))
        ->toBe(hash('sha256', 'key'.'secret'.'1700000000'));
});

it('rejects missing hbx credentials without printing secrets', function () {
    config(['services.hbx.api_secret' => null]);

    expect(fn () => hbx()->healthCheck())->toThrow(SupplierAuthenticationException::class);
});

it('maps invalid hbx credentials to a domain exception', function () {
    Http::fake(['*' => Http::response(['error' => 'forbidden'], 401)]);

    expect(fn () => hbx()->healthCheck())->toThrow(SupplierAuthenticationException::class);
});

it('maps hbx rate limits to a domain exception', function () {
    Http::fake(['*' => Http::response(['error' => 'too many'], 429)]);

    expect(fn () => hbx()->healthCheck())->toThrow(SupplierRateLimitException::class);
});

it('maps hbx timeouts safely', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(fn () => hbx()->healthCheck())->toThrow(SupplierTimeoutException::class);
});

it('maps malformed hbx responses safely', function () {
    Http::fake(['*' => Http::response('not-json', 404)]);

    expect(fn () => hbx()->healthCheck())->toThrow(InvalidSupplierResponseException::class);

    $log = SupplierOperationLog::query()->latest('id')->firstOrFail();

    expect($log->response_status)->toBe(404)
        ->and($log->error_type->value)->toBe('invalid_response');
});

it('uses the exact hbx status URL and safe diagnostic timeouts', function () {
    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $supplier->forceFill([
        'timeout_seconds' => 30,
        'connect_timeout_seconds' => 5,
        'max_retries' => 3,
    ])->save();

    $adapter = hbx();

    expect($adapter)->toBeInstanceOf(HbxHotelSupplier::class);

    $diagnostics = $adapter->healthDiagnostics();

    expect(app(HbxHttpClient::class)->fullUrl($supplier, '/hotel-api/1.0/status'))
        ->toBe('https://api.test.hotelbeds.com/hotel-api/1.0/status')
        ->and($diagnostics->targetHost)->toBe('api.test.hotelbeds.com')
        ->and($diagnostics->targetPath)->toBe('/hotel-api/1.0/status')
        ->and($diagnostics->method)->toBe('GET')
        ->and($diagnostics->connectTimeoutSeconds)->toBe(15)
        ->and($diagnostics->timeoutSeconds)->toBe(45);
});

it('does not retry the hbx health status request', function () {
    Supplier::query()->where('code', 'hbx_hotels')->update(['max_retries' => 3]);
    Http::fake(['*' => Http::response(['status' => 'OK'], 200)]);

    hbx()->healthCheck();

    Http::assertSentCount(1);
});

it('normalizes hbx availability with bookable and recheck rates', function () {
    Http::fake(['*' => Http::response(availabilityPayload(), 200)]);

    $result = hbx()->search(searchRequest());

    expect($result->supplierCode)->toBe('hbx_hotels')
        ->and($result->hotels)->toHaveCount(1)
        ->and($result->hotels[0]->supplierHotelId)->toBe('1001')
        ->and($result->hotels[0]->rooms)->toHaveCount(2)
        ->and($result->hotels[0]->rooms[0]->refundability)->toBe(RateRefundability::Refundable)
        ->and($result->hotels[0]->rooms[1]->metadata['requires_check_rate'])->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Api-key')
        && $request->hasHeader('X-Signature')
        && $request->hasHeader('Accept', 'application/json')
        && data_get($request->data(), 'destination.code') === 'CAI'
        && data_get($request->data(), 'occupancies.0.paxes') === null);
});

it('uses hbx hotel-code availability without mixing destination filters', function () {
    Http::fake(['*' => Http::response(availabilityPayload(), 200)]);

    hbx()->search(new HotelSearchRequestData(
        destinationIdentifier: 'CAI',
        checkIn: now()->toImmutable()->addDay(),
        checkOut: now()->toImmutable()->addDays(2),
        rooms: [new RoomOccupancyData(1, 1, [8])],
        currency: 'EGP',
        locale: 'ar',
        metadata: ['hotel_codes' => ['1001', '1001', '']],
    ));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && $request->method() === 'POST'
        && data_get($request->data(), 'destination.code') === null
        && data_get($request->data(), 'hotels.hotel') === [1001]
        && data_get($request->data(), 'occupancies.0.paxes.0.type') === 'CH'
        && data_get($request->data(), 'occupancies.0.paxes.0.age') === 8);
});

it('normalizes hbx check rate and detects price changes', function () {
    Http::fake(['*' => Http::response(checkRatePayload('130.00'), 200)]);

    $result = hbx()->checkRate(new CheckRateRequestData(
        supplierHotelId: '1001',
        supplierRateKey: 'hbx-rate-bookable',
        selectedRooms: [['total' => ['minor_amount' => 12000]]],
        occupancy: [new RoomOccupancyData(2)],
        currency: 'EGP',
    ));

    expect($result->available)->toBeTrue()
        ->and($result->priceChanged)->toBeTrue()
        ->and($result->confirmedTotal?->minorAmount)->toBe(13000)
        ->and($result->confirmedRateKey)->toBe('hbx-rate-checked');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/checkrates'
        && $request->method() === 'POST'
        && data_get($request->data(), 'rooms') === [['rateKey' => 'hbx-rate-bookable']]);
});

it('normalizes booking confirmation', function () {
    Http::fake(['*' => Http::response(['booking' => ['reference' => 'HBX-1', 'status' => 'CONFIRMED', 'totalNet' => '120.00', 'currency' => 'EGP']], 200)]);

    $confirmed = hbx()->book(bookingRequest('idem-confirmed'));

    expect($confirmed->successful)->toBeTrue()
        ->and($confirmed->status)->toBe(BookingSupplierStatus::Confirmed)
        ->and($confirmed->supplierBookingReference)->toBe('HBX-1');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/bookings'
        && $request->method() === 'POST'
        && data_get($request->data(), 'holder.name') === 'Ali'
        && data_get($request->data(), 'clientReference') === 'idem-confirmed'
        && data_get($request->data(), 'rooms.0.rateKey') === 'hbx-rate-checked'
        && data_get($request->data(), 'rooms.0.paxes.0.roomId') === 1
        && data_get($request->data(), 'rooms.0.paxes.0.type') === 'AD'
        && data_get($request->data(), 'rooms.0.paxes.0.age') === null);
});

it('normalizes booking rejection', function () {
    Http::fake(['*' => Http::response(['booking' => ['status' => 'REJECTED', 'currency' => 'EGP']], 200)]);

    $rejected = hbx()->book(bookingRequest('idem-rejected'));

    expect($rejected->successful)->toBeFalse()
        ->and($rejected->status)->toBe(BookingSupplierStatus::Rejected);
});

it('marks booking timeout outcomes for manual review', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $timeout = hbx()->book(bookingRequest('idem-timeout'));

    expect($timeout->status)->toBe(BookingSupplierStatus::Uncertain)
        ->and($timeout->requiresManualReview)->toBeTrue();
});

it('normalizes booking lookup reconciliation', function () {
    Http::fake(['*' => Http::response(['booking' => ['reference' => 'HBX-1', 'status' => 'CONFIRMED', 'currency' => 'EGP', 'hotel' => ['code' => '1001', 'name' => 'HBX Cairo', 'rooms' => []], 'totalNet' => '120.00']], 200)]);

    $result = hbx()->getBooking(new SupplierBookingLookupRequestData('HBX-1'));

    expect($result->found)->toBeTrue()
        ->and($result->status)->toBe(BookingSupplierStatus::Confirmed)
        ->and($result->totals['total']->minorAmount)->toBe(12000);
});

it('normalizes cancellation success, penalty, and unknown timeout', function () {
    Http::fake(['*' => Http::response(['booking' => ['reference' => 'HBX-1', 'status' => 'CANCELLED', 'currency' => 'EGP', 'cancellationAmount' => '30.00']], 200)]);

    $cancelled = hbx()->cancel(new SupplierCancellationRequestData('HBX-1', 'cancel-1', metadata: ['cancellation_flag' => 'CANCELLATION']));

    expect($cancelled->successful)->toBeTrue()
        ->and($cancelled->status)->toBe(CancellationSupplierStatus::Cancelled)
        ->and($cancelled->penaltyAmount?->minorAmount)->toBe(3000);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/bookings/HBX-1?cancellationFlag=CANCELLATION'
        && $request->method() === 'DELETE');

    Http::fake(fn () => throw new ConnectionException('timeout'));

    $unknown = hbx()->cancel(new SupplierCancellationRequestData('HBX-2', 'cancel-2', metadata: ['cancellation_flag' => 'CANCELLATION']));

    expect($unknown->requiresManualReview)->toBeTrue()
        ->and($unknown->status)->toBe(CancellationSupplierStatus::Pending);
});

it('redacts hbx credentials and signatures from operation logs', function () {
    Http::fake(['*' => Http::response(['status' => 'OK'], 200)]);

    hbx()->healthCheck();

    $log = SupplierOperationLog::query()->latest('id')->firstOrFail();

    expect($log->request_headers['Api-key'])->toBe('[REDACTED]')
        ->and($log->request_headers['X-Signature'])->toBe('[REDACTED]')
        ->and(json_encode($log->request_headers))->not->toContain('test-api-key')
        ->and(json_encode($log->request_headers))->not->toContain('test-api-secret');
});

it('keeps hbx supplier ids and rate keys behind public tokens in search snapshots', function () {
    Http::fake(['*' => Http::response(availabilityPayload(), 200)]);
    config(['travel.public_search.suppliers' => ['hbx_hotels']]);
    $city = City::query()->where('is_active', true)->firstOrFail();

    $session = app(HotelSearchService::class)->search([
        'destination' => 'city:'.$city->id,
        'check_in' => now()->addDay()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'rooms' => 1,
        'adults' => 2,
        'children' => 0,
        'currency' => 'EGP',
        'locale' => 'ar',
    ]);

    expect($session->warnings)->toBe([], SupplierOperationLog::query()->latest('id')->value('error_message') ?? 'no supplier log');
    expect($session->results_snapshot)->not->toBeEmpty();

    $hotel = $session->results_snapshot[0];

    expect($hotel['supplier_code'])->toBe('hbx_hotels')
        ->and($hotel['public_token'])->not->toBe($hotel['supplier_hotel_id'])
        ->and($hotel['rates'][0]['public_rate_token'])->not->toBe($hotel['rates'][0]['supplier_rate_key']);
});

it('keeps the mock supplier functional', function () {
    $result = app(SupplierManager::class)->resolve('mock_hotels', SupplierOperation::Search)->search(searchRequest());

    expect($result->supplierCode)->toBe('mock_hotels')
        ->and($result->hotels)->not->toBeEmpty();
});

function hbx(): HotelSupplierInterface
{
    return app(SupplierManager::class)->resolve('hbx_hotels');
}

function searchRequest(): HotelSearchRequestData
{
    return new HotelSearchRequestData(
        destinationIdentifier: 'CAI',
        checkIn: now()->toImmutable()->addDay(),
        checkOut: now()->toImmutable()->addDays(2),
        rooms: [new RoomOccupancyData(2)],
        currency: 'EGP',
        locale: 'ar',
    );
}

function hbxSupplierSeedMapping(): void
{
    $city = City::query()->where('name_en', 'Cairo')->first() ?? City::query()->where('is_active', true)->firstOrFail();

    HbxDestination::query()->updateOrCreate(
        ['supplier_code' => 'hbx_hotels', 'destination_code' => 'CAI'],
        ['destination_name' => 'Cairo', 'country_code' => 'EG', 'is_active' => true, 'synced_at' => now()],
    );

    HbxHotel::query()->updateOrCreate(
        ['supplier_code' => 'hbx_hotels', 'hotel_code' => '1001'],
        ['destination_code' => 'CAI', 'hotel_name' => 'HBX Cairo Sandbox Hotel', 'category_code' => '5EST', 'star_rating' => 5, 'is_active' => true, 'synced_at' => now()],
    );

    SupplierDestinationMapping::query()->updateOrCreate(
        ['local_entity_type' => 'city', 'local_entity_id' => $city->id, 'supplier_code' => 'hbx_hotels', 'supplier_destination_code' => 'CAI'],
        ['status' => 'confirmed', 'confidence' => 100, 'manually_confirmed' => true, 'is_active' => true],
    );
}

function bookingRequest(string $idempotencyKey): SupplierBookingRequestData
{
    $lead = new GuestData('Ali', 'Hassan', isLead: true);

    return new SupplierBookingRequestData(
        idempotencyKey: $idempotencyKey,
        supplierRateKey: 'hbx-rate-checked',
        supplierHotelId: '1001',
        rooms: [['name' => 'Standard']],
        leadGuest: $lead,
        guests: [$lead],
        customerContactData: ['email' => 'customer@example.test'],
        expectedTotal: Money::fromDecimalString('120.00', 'EGP'),
        correlationId: (string) Str::uuid(),
    );
}

function availabilityPayload(): array
{
    return ['hotels' => ['hotels' => [[
        'code' => 1001,
        'name' => 'HBX Cairo Sandbox Hotel',
        'categoryCode' => '5EST',
        'destinationCode' => 'CAI',
        'zoneName' => 'Cairo',
        'latitude' => '30.0444',
        'longitude' => '31.2357',
        'rooms' => [[
            'code' => 'STD',
            'name' => 'Standard Room',
            'rates' => [
                ['rateKey' => 'hbx-rate-bookable', 'rateType' => 'BOOKABLE', 'rateClass' => 'NOR', 'net' => '100.00', 'sellingRate' => '120.00', 'currency' => 'EGP', 'boardCode' => 'BB', 'allotment' => 3, 'cancellationPolicies' => [['amount' => '20.00', 'from' => now()->addDay()->toIso8601String()]]],
                ['rateKey' => 'hbx-rate-recheck', 'rateType' => 'RECHECK', 'rateClass' => 'NRF', 'net' => '90.00', 'sellingRate' => '110.00', 'currency' => 'EGP', 'boardCode' => 'RO'],
            ],
        ]],
    ]]]];
}

function checkRatePayload(string $amount): array
{
    $payload = availabilityPayload();
    $payload['hotel'] = $payload['hotels']['hotels'][0];
    $payload['hotel']['rooms'][0]['rates'] = [[
        'rateKey' => 'hbx-rate-checked',
        'rateType' => 'BOOKABLE',
        'rateClass' => 'NOR',
        'net' => '100.00',
        'sellingRate' => $amount,
        'currency' => 'EGP',
        'boardCode' => 'BB',
        'cancellationPolicies' => [['amount' => '10.00', 'from' => now()->addDay()->toIso8601String()]],
    ]];

    return $payload;
}
