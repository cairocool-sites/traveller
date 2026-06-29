<?php

use App\Enums\RateCheckStatus;
use App\Enums\SupplierStatus;
use App\Livewire\HotelSearchForm;
use App\Models\City;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use App\Models\SupplierOperationLog;
use App\Services\Booking\BookingFlowException;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\PublicSearch\DestinationLookupService;
use App\Services\PublicSearch\HotelSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->setLocale('en');

    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'phase12-api-key',
        'services.hbx.api_secret' => 'phase12-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
        'travel.public_search.suppliers' => ['hbx_hotels', 'mock_hotels'],
        'travel.public_search.markup_basis_points' => 0,
    ]);

    $this->seed();

    Supplier::query()->where('code', 'hbx_hotels')->update(['status' => SupplierStatus::Active]);
    phase12SeedHbxMapping();
});

it('maps public search criteria to hbx availability and stores normalized safe offers', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);

    $session = phase12SearchSession(['children' => 1, 'child_ages' => [7]]);
    $hotel = $session->results_snapshot[0];
    $bookable = $hotel['rates'][0];
    $recheck = $hotel['rates'][1];

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && $request->method() === 'POST'
        && data_get($request->data(), 'destination.code') === null
        && data_get($request->data(), 'hotels.hotel.0') === 1001
        && data_get($request->data(), 'language') === 'ENG'
        && data_get($request->data(), 'occupancies.0.children') === 1
        && data_get($request->data(), 'occupancies.0.paxes.0.age') === 7);

    expect($hotel['supplier_code'])->toBe('hbx_hotels')
        ->and($hotel['supplier_hotel_id'])->toBe('1001')
        ->and($hotel['name'])->toBe('HBX Cairo Sandbox Hotel')
        ->and($hotel['minimum_price_minor'])->toBe(11000)
        ->and($bookable['supplier_rate_key'])->toBe('hbx-rate-bookable')
        ->and($bookable['public_rate_token'])->not->toBe($bookable['supplier_rate_key'])
        ->and($bookable['net']['minor_amount'])->toBe(10000)
        ->and($bookable['supplier_total']['minor_amount'])->toBe(12000)
        ->and($bookable['total']['minor_amount'])->toBe(12000)
        ->and($bookable['requires_check_rate'])->toBeFalse()
        ->and($bookable['rate_type'])->toBe('BOOKABLE')
        ->and($recheck['requires_check_rate'])->toBeTrue()
        ->and($recheck['rate_type'])->toBe('RECHECK')
        ->and($bookable)->toHaveKeys(['payment_type', 'availability_timestamp', 'rate_expires_at']);
});

it('serves public autocomplete from local hbx catalogue without supplier calls', function () {
    Http::preventStrayRequests();

    HbxDestination::query()->where('destination_code', 'CAI')->update([
        'public_enabled' => true,
        'supplier_active' => true,
        'name_en' => 'Cairo',
        'name_ar' => 'القاهرة',
        'slug' => 'cairo-cai',
    ]);
    HbxHotel::query()->where('hotel_code', '1001')->update([
        'public_enabled' => true,
        'supplier_active' => true,
        'name_en' => 'HBX Cairo Sandbox Hotel',
        'name_ar' => 'فندق القاهرة التجريبي',
        'slug' => 'hbx-cairo-sandbox-hotel-1001',
    ]);

    $english = app(DestinationLookupService::class)->search('Cairo', 'en');
    $arabic = app(DestinationLookupService::class)->search('القاهره', 'ar');

    expect($english->pluck('type')->all())->toContain('hbx_destination', 'hbx_hotel')
        ->and($arabic->first()?->label)->not->toBeNull();
});

it('labels hotel and destination autocomplete suggestions for customers', function () {
    Http::preventStrayRequests();

    HbxDestination::query()->where('destination_code', 'CAI')->update([
        'supplier_active' => true,
        'public_enabled' => true,
        'name_en' => 'Cairo',
    ]);
    HbxHotel::query()->where('hotel_code', '1001')->update([
        'supplier_active' => true,
        'public_enabled' => true,
        'name_en' => 'HBX Cairo Sandbox Hotel',
    ]);

    Livewire::test(HotelSearchForm::class, ['locale' => 'en'])
        ->set('destinationTerm', 'Cairo')
        ->assertSee('Destination')
        ->assertSee('Hotel')
        ->assertSee('Search live availability for this hotel');
});

it('searches hbx by local public destination using destination code without mapping dependency', function () {
    SupplierDestinationMapping::query()->delete();
    HbxDestination::query()->where('destination_code', 'CAI')->update([
        'public_enabled' => true,
        'supplier_active' => true,
        'name_en' => 'Cairo',
        'slug' => 'cairo-cai',
    ]);
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);
    $destination = HbxDestination::query()->where('destination_code', 'CAI')->firstOrFail();

    $session = phase12SearchSession(['destination' => "hbx_destination:{$destination->id}"]);

    expect($session->results_snapshot)->not->toBeEmpty();
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && data_get($request->data(), 'destination.code') === 'CAI'
        && data_get($request->data(), 'hotels.hotel') === null);
});

it('searches hbx by local public hotel using protected hotel code list', function () {
    HbxHotel::query()->where('hotel_code', '1001')->update([
        'public_enabled' => true,
        'supplier_active' => true,
        'name_en' => 'HBX Cairo Sandbox Hotel',
        'slug' => 'hbx-cairo-sandbox-hotel-1001',
    ]);
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);
    $hotel = HbxHotel::query()->where('hotel_code', '1001')->firstOrFail();

    $session = phase12SearchSession(['destination' => "hbx_hotel:{$hotel->id}"]);

    expect($session->results_snapshot[0]['name'])->toBe('HBX Cairo Sandbox Hotel');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && data_get($request->data(), 'destination.code') === null
        && data_get($request->data(), 'hotels.hotel') === [1001]);
});

it('applies configured customer selling markup without mutating supplier totals', function () {
    config(['travel.public_search.markup_basis_points' => 1000]);
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);

    $session = phase12SearchSession();
    $rate = $session->results_snapshot[0]['rates'][0];

    expect($rate['supplier_total']['minor_amount'])->toBe(12000)
        ->and($rate['total']['minor_amount'])->toBe(13200)
        ->and($session->results_snapshot[0]['minimum_price_minor'])->toBe(12100);
});

it('supports bookable and recheck checkrate with fresh cancellation snapshots', function (string $rateType) {
    $confirmedAmount = $rateType === 'RECHECK' ? '110.00' : '120.00';

    Http::fakeSequence()
        ->push(phase12AvailabilityPayload())
        ->push(phase12CheckRatePayload($confirmedAmount, '10.00'));

    $session = phase12SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = collect($hotel['rates'])->firstWhere('rate_type', $rateType);
    $check = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($check->status)->toBe(RateCheckStatus::Available)
        ->and($check->checked_amount_minor)->toBe($rateType === 'RECHECK' ? 11000 : 12000)
        ->and($check->price_changed)->toBeFalse()
        ->and($check->cancellation_policy_snapshot[0]['penalty_amount']['minor_amount'])->toBe(1000)
        ->and($check->supplier_reference_snapshot['confirmed_rate_key'])->toBe('hbx-rate-checked');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/checkrates'
        && $request->method() === 'POST'
        && data_get($request->data(), 'rooms.0.rateKey') === $rate['supplier_rate_key']);
})->with(['BOOKABLE', 'RECHECK']);

it('detects hbx checkrate price changes cancellation changes and unavailable rates', function () {
    Http::fakeSequence()
        ->push(phase12AvailabilityPayload())
        ->push(phase12CheckRatePayload('130.00', '30.00'))
        ->push(phase12UnavailableCheckRatePayload());

    $session = phase12SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];

    $priceChanged = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($priceChanged->status)->toBe(RateCheckStatus::PriceChanged)
        ->and($priceChanged->price_changed)->toBeTrue()
        ->and($priceChanged->checked_amount_minor)->toBe(13000)
        ->and($priceChanged->cancellation_policy_snapshot[0]['penalty_amount']['minor_amount'])->toBe(3000);

    $unavailable = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($unavailable->status)->toBe(RateCheckStatus::RateExpired)
        ->and($unavailable->supplier_reference_snapshot['failure_reason'])->toBe('rate_expired');
});

it('does not silently fall back to mock supplier on hbx rate limits', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['message' => 'Too many requests'], 429)]);

    $session = phase12SearchSession();

    expect($session->results_snapshot)->toBe([])
        ->and(implode(' ', $session->warnings))->toContain('busy');
});

it('does not silently fall back to mock supplier on hbx timeouts', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $session = phase12SearchSession();

    expect($session->results_snapshot)->toBe([])
        ->and(implode(' ', $session->warnings))->toContain('timed out');
});

it('fails safely when no confirmed hbx destination mapping exists', function () {
    SupplierDestinationMapping::query()->delete();
    Http::fake();

    $session = phase12SearchSession();

    expect($session->results_snapshot)->toBe([])
        ->and(implode(' ', $session->warnings))->toContain('could not complete');

    Http::assertNothingSent();
});

it('fails safely when no synchronized hbx hotel codes exist', function () {
    HbxHotel::query()->delete();
    Http::fake();

    $session = phase12SearchSession();

    expect($session->results_snapshot)->toBe([])
        ->and(implode(' ', $session->warnings))->toContain('could not complete');

    Http::assertNothingSent();
});

it('rejects invalid occupancy before sending an hbx request', function () {
    Http::fake();

    expect(fn () => phase12SearchSession(['children' => 2, 'child_ages' => [5]]))
        ->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

it('rejects expired tampered and cross-session offer references', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);

    $first = phase12SearchSession();
    $second = phase12SearchSession(['check_in' => now()->addDays(15)->toDateString(), 'check_out' => now()->addDays(17)->toDateString()]);
    $hotel = $first->results_snapshot[0];
    $rate = $hotel['rates'][0];

    $first->forceFill(['expires_at' => now()->subMinute()])->save();
    expect(fn () => app(RateCheckService::class)->check($first, $hotel['public_token'], $rate['public_rate_token']))
        ->toThrow(BookingFlowException::class);

    $first->forceFill(['expires_at' => now()->addMinutes(10)])->save();
    expect(fn () => app(RateCheckService::class)->check($first, $hotel['public_token'], 'tampered-token'))
        ->toThrow(BookingFlowException::class);

    expect(fn () => app(RateCheckService::class)->check($second, $hotel['public_token'], $rate['public_rate_token']))
        ->toThrow(BookingFlowException::class);
});

it('redacts credentials in hbx search logs and never exposes raw hbx tokens in public HTML', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(phase12AvailabilityPayload(), 200)]);

    $this->get(route('hotels.search', phase12Criteria()))->assertOk()
        ->assertSee('HBX Cairo Sandbox Hotel')
        ->assertSee('Bookable')
        ->assertSee('Price requires recheck')
        ->assertDontSee('hbx-rate-bookable')
        ->assertDontSee('phase12-api-key')
        ->assertDontSee('phase12-api-secret');

    $log = SupplierOperationLog::query()
        ->where('request_url', '/hotel-api/1.0/hotels')
        ->latest('id')
        ->firstOrFail();
    $headers = json_encode($log->request_headers, JSON_THROW_ON_ERROR);

    expect($headers)->not->toContain('phase12-api-key')
        ->and($headers)->not->toContain('phase12-api-secret')
        ->and($headers)->toContain('[REDACTED]');
});

it('blocks public hbx booking submission and does not call the hbx booking endpoint', function () {
    Http::fakeSequence()
        ->push(phase12AvailabilityPayload())
        ->push(phase12CheckRatePayload('120.00', '10.00'));

    $session = phase12SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];
    $check = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect(fn () => app(BookingService::class)->createAndSubmit($check, phase12BookingPayload()))
        ->toThrow(BookingFlowException::class, 'HBX sandbox booking submission is disabled');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

function phase12Criteria(array $overrides = []): array
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

    return array_merge([
        'destination' => "city:{$city->id}",
        'check_in' => now()->addDays(8)->toDateString(),
        'check_out' => now()->addDays(10)->toDateString(),
        'rooms' => 1,
        'adults' => 2,
        'children' => 0,
        'currency' => 'EGP',
        'locale' => 'en',
    ], $overrides);
}

function phase12SearchSession(array $overrides = []): SearchSession
{
    return app(HotelSearchService::class)->search(phase12Criteria($overrides), 'phase12-session');
}

function phase12SeedHbxMapping(): void
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

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

function phase12AvailabilityPayload(): array
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
                ['rateKey' => 'hbx-rate-bookable', 'rateType' => 'BOOKABLE', 'rateClass' => 'NOR', 'net' => '100.00', 'sellingRate' => '120.00', 'currency' => 'EGP', 'boardCode' => 'BB', 'paymentType' => 'AT_WEB', 'allotment' => 3, 'cancellationPolicies' => [['amount' => '20.00', 'from' => now()->addDay()->toIso8601String()]]],
                ['rateKey' => 'hbx-rate-recheck', 'rateType' => 'RECHECK', 'rateClass' => 'NRF', 'net' => '90.00', 'sellingRate' => '110.00', 'currency' => 'EGP', 'boardCode' => 'RO', 'paymentType' => 'AT_WEB'],
            ],
        ]],
    ]]]];
}

function phase12CheckRatePayload(string $amount, string $penaltyAmount): array
{
    $payload = phase12AvailabilityPayload();
    $payload['hotel'] = $payload['hotels']['hotels'][0];
    $payload['hotel']['rooms'][0]['rates'] = [[
        'rateKey' => 'hbx-rate-checked',
        'rateType' => 'BOOKABLE',
        'rateClass' => 'NOR',
        'net' => '100.00',
        'sellingRate' => $amount,
        'currency' => 'EGP',
        'boardCode' => 'BB',
        'paymentType' => 'AT_WEB',
        'cancellationPolicies' => [['amount' => $penaltyAmount, 'from' => now()->addDay()->toIso8601String()]],
    ]];

    return $payload;
}

function phase12UnavailableCheckRatePayload(): array
{
    return ['hotel' => ['rooms' => [['code' => 'STD', 'name' => 'Standard Room', 'rates' => []]]]];
}

function phase12BookingPayload(array $overrides = []): array
{
    return array_merge([
        'contact_email' => 'guest@example.test',
        'contact_phone' => '+201000000000',
        'idempotency_key' => (string) Str::uuid(),
        'accept_price_change' => true,
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
        ],
    ], $overrides);
}
