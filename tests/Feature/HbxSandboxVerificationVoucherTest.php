<?php

use App\Enums\BookingStatus;
use App\Enums\RateCheckStatus;
use App\Enums\SupplierStatus;
use App\Models\Booking;
use App\Models\City;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\PublicSearch\HotelSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app()->setLocale('en');
    Notification::fake();
    Http::preventStrayRequests();

    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'phase14-api-key',
        'services.hbx.api_secret' => 'phase14-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
        'services.hbx.sandbox_booking_enabled' => false,
        'travel.public_search.suppliers' => ['hbx_hotels'],
        'travel.public_search.markup_basis_points' => 0,
    ]);

    $this->seed();

    Supplier::query()->where('code', 'hbx_hotels')->update([
        'status' => SupplierStatus::Active,
        'base_url' => null,
        'max_retries' => 2,
    ]);

    phase14SeedHbxMapping();
});

it('blocks manual verification while the sandbox booking guard is disabled', function () {
    $this->artisan('hbx:verify-sandbox-booking --dry-run')
        ->expectsOutputToContain('HBX sandbox booking verification is disabled')
        ->assertFailed();

    Http::assertNothingSent();
});

it('blocks production hbx endpoints before search or booking', function () {
    config([
        'services.hbx.sandbox_booking_enabled' => true,
        'services.hbx.base_url' => 'https://api.hotelbeds.com',
    ]);

    $this->artisan('hbx:verify-sandbox-booking --dry-run')
        ->expectsOutputToContain('configured endpoint is not https://api.test.hotelbeds.com')
        ->assertFailed();

    Http::assertNothingSent();
});

it('runs dry-run through CheckRate and sends no booking request', function () {
    config(['services.hbx.sandbox_booking_enabled' => true]);
    Http::fakeSequence()
        ->push(phase14AvailabilityPayload())
        ->push(phase14CheckRatePayload());

    $this->artisan('hbx:verify-sandbox-booking --dry-run')
        ->expectsConfirmation('This command is for one controlled HBX sandbox verification only. Continue?', 'yes')
        ->expectsOutputToContain('Supplier: hbx_hotels')
        ->expectsOutputToContain('Local destination:')
        ->expectsOutputToContain('HBX destination code: CAI')
        ->expectsOutputToContain('Number of hotel codes searched: 1')
        ->expectsOutputToContain('Availability result count: 1')
        ->expectsOutputToContain('CheckRate source: HBX Sandbox')
        ->expectsOutputToContain('Selling total:')
        ->expectsOutputToContain('Dry run complete; no booking request sent.')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/checkrates'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('dry-run refuses public mock fallback and still uses hbx_hotels', function () {
    config([
        'services.hbx.sandbox_booking_enabled' => true,
        'travel.public_search.suppliers' => ['mock_hotels'],
    ]);

    Http::fakeSequence()
        ->push(phase14AvailabilityPayload())
        ->push(phase14CheckRatePayload());

    $this->artisan('hbx:verify-sandbox-booking --dry-run')
        ->expectsConfirmation('This command is for one controlled HBX sandbox verification only. Continue?', 'yes')
        ->expectsOutputToContain('Supplier: hbx_hotels')
        ->expectsOutputToContain('HBX destination code: CAI')
        ->expectsOutputToContain('Number of hotel codes searched: 1')
        ->expectsOutputToContain('CheckRate source: HBX Sandbox')
        ->doesntExpectOutputToContain('Mock Cairo Nile Hotel')
        ->expectsOutputToContain('Dry run complete; no booking request sent.')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && data_get($request->data(), 'destination.code') === 'CAI'
        && data_get($request->data(), 'hotels.hotel.0') === '1001'
        && data_get($request->data(), 'destination.code') !== 'Cairo');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/checkrates');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('fails safely when hbx availability is unavailable instead of using mock fallback', function () {
    config([
        'services.hbx.sandbox_booking_enabled' => true,
        'travel.public_search.suppliers' => ['mock_hotels'],
    ]);

    Http::fake(fn () => Http::response(['error' => 'unavailable'], 503));

    $this->artisan('hbx:verify-sandbox-booking --dry-run')
        ->expectsConfirmation('This command is for one controlled HBX sandbox verification only. Continue?', 'yes')
        ->expectsOutputToContain('HBX sandbox availability search returned no offers')
        ->doesntExpectOutputToContain('Mock Cairo Nile Hotel')
        ->assertFailed();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/checkrates'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('requires CheckRate before one manual sandbox booking request', function () {
    config(['services.hbx.sandbox_booking_enabled' => true]);
    Http::fakeSequence()
        ->push(phase14AvailabilityPayload())
        ->push(phase14CheckRatePayload())
        ->push(phase14BookingResponse());

    $this->artisan('hbx:verify-sandbox-booking')
        ->expectsConfirmation('This command is for one controlled HBX sandbox verification only. Continue?', 'yes')
        ->expectsOutputToContain('Selling total:')
        ->expectsConfirmation('Send exactly one HBX sandbox booking request now?', 'yes')
        ->expectsOutputToContain('Local reference:')
        ->expectsOutputToContain('HBX reference: HBX-PHASE14-BOOKING')
        ->assertSuccessful();

    $checkRateCalls = Http::recorded(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/checkrates'));
    $bookingCalls = Http::recorded(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));

    expect($checkRateCalls)->toHaveCount(1)
        ->and($bookingCalls)->toHaveCount(1);
});

it('does not retry ambiguous booking timeouts', function () {
    config(['services.hbx.sandbox_booking_enabled' => true]);
    $call = 0;

    Http::fake(function () use (&$call) {
        $call++;

        return match ($call) {
            1 => Http::response(phase14AvailabilityPayload(), 200),
            2 => Http::response(phase14CheckRatePayload(), 200),
            default => throw new ConnectionException('timeout'),
        };
    });

    $this->artisan('hbx:verify-sandbox-booking')
        ->expectsConfirmation('This command is for one controlled HBX sandbox verification only. Continue?', 'yes')
        ->expectsConfirmation('Send exactly one HBX sandbox booking request now?', 'yes')
        ->expectsOutputToContain('manual lookup')
        ->assertSuccessful();

    expect($call)->toBe(3);
});

it('protects vouchers with booking authorization', function () {
    $booking = phase14ConfirmedBooking();
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->get(route('admin.bookings.voucher', $booking))
        ->assertForbidden();
});

it('renders confirmed internal voucher without sensitive values', function () {
    $booking = phase14ConfirmedBooking();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.bookings.voucher', $booking))
        ->assertOk()
        ->assertSee('Cairo Cool Travel')
        ->assertSee('Sandbox / Test Booking')
        ->assertSee($booking->booking_reference)
        ->assertSee('HBX-PHASE14-BOOKING')
        ->assertSee('120.00 EGP')
        ->assertDontSee('phase14-api-key')
        ->assertDontSee('phase14-api-secret')
        ->assertDontSee($booking->contact_email)
        ->assertDontSee($booking->contact_phone)
        ->assertDontSee($booking->supplier_rate_reference)
        ->assertDontSee('totalNet');
});

it('rejects final vouchers for unconfirmed non-review bookings', function () {
    $booking = phase14ConfirmedBooking();
    $booking->forceFill(['status' => BookingStatus::Draft])->save();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.bookings.voucher', $booking))
        ->assertNotFound();
});

it('renders a provisional notice for manual-review bookings', function () {
    $booking = phase14ConfirmedBooking();
    $booking->forceFill(['status' => BookingStatus::ManualReview])->save();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.bookings.voucher', $booking))
        ->assertOk()
        ->assertSee('Provisional manual-review notice')
        ->assertDontSee('Sandbox / Test Booking');
});

it('downloads printable voucher fallback with a safe filename', function () {
    $booking = phase14ConfirmedBooking();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.bookings.voucher', ['booking' => $booking, 'download' => true]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="cairo-cool-travel-voucher-'.Str::of($booking->booking_reference)->lower().'.html"');
});

function phase14Criteria(array $overrides = []): array
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

    return array_merge([
        'destination' => "city:{$city->id}",
        'check_in' => now()->addDays(21)->toDateString(),
        'check_out' => now()->addDays(22)->toDateString(),
        'rooms' => 1,
        'adults' => 2,
        'children' => 0,
        'currency' => 'EGP',
        'locale' => 'en',
        'nationality' => 'EG',
        'residency_country' => 'EG',
    ], $overrides);
}

function phase14SearchSession(array $overrides = []): SearchSession
{
    return app(HotelSearchService::class)->search(phase14Criteria($overrides), 'phase14-session-'.Str::random(6));
}

function phase14SeedHbxMapping(): void
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

    HbxDestination::query()->updateOrCreate(
        ['supplier_code' => 'hbx_hotels', 'destination_code' => 'CAI'],
        ['destination_name' => 'Cairo', 'country_code' => 'EG', 'is_active' => true, 'synced_at' => now()],
    );

    HbxHotel::query()->updateOrCreate(
        ['supplier_code' => 'hbx_hotels', 'hotel_code' => '1001'],
        ['destination_code' => 'CAI', 'hotel_name' => 'HBX Phase 14 Sandbox Hotel', 'category_code' => '5EST', 'star_rating' => 5, 'is_active' => true, 'synced_at' => now()],
    );

    SupplierDestinationMapping::query()->updateOrCreate(
        ['local_entity_type' => 'city', 'local_entity_id' => $city->id, 'supplier_code' => 'hbx_hotels', 'supplier_destination_code' => 'CAI'],
        ['status' => 'confirmed', 'confidence' => 100, 'manually_confirmed' => true, 'is_active' => true],
    );
}

function phase14RateCheckUsingCurrentFake(): RateCheck
{
    $session = phase14SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];
    $rateCheck = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($rateCheck->status)->toBe(RateCheckStatus::Available);

    return $rateCheck;
}

function phase14ConfirmedBooking(): Booking
{
    config(['services.hbx.sandbox_booking_enabled' => true]);
    Http::fakeSequence()
        ->push(phase14AvailabilityPayload())
        ->push(phase14CheckRatePayload())
        ->push(phase14BookingResponse());

    $rateCheck = phase14RateCheckUsingCurrentFake();

    return app(BookingService::class)->createAndSubmit($rateCheck, phase14BookingPayload());
}

function phase14BookingPayload(array $overrides = []): array
{
    return array_merge([
        'contact_email' => 'sandbox.guest@example.test',
        'contact_phone' => '+200000000000',
        'customer_nationality' => 'EG',
        'idempotency_key' => (string) Str::uuid(),
        'accept_price_change' => true,
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Tester', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Traveler', 'is_lead_guest' => false],
        ],
    ], $overrides);
}

function phase14AvailabilityPayload(): array
{
    return ['hotels' => ['hotels' => [[
        'code' => 1401,
        'name' => 'HBX Phase 14 Sandbox Hotel',
        'categoryCode' => '5EST',
        'destinationCode' => 'CAI',
        'zoneName' => 'Cairo',
        'rooms' => [[
            'code' => 'STD',
            'name' => 'Sandbox Standard Room',
            'rates' => [[
                'rateKey' => 'phase14-rate-bookable',
                'rateType' => 'BOOKABLE',
                'rateClass' => 'NOR',
                'net' => '100.00',
                'sellingRate' => '120.00',
                'currency' => 'EGP',
                'boardCode' => 'BB',
                'paymentType' => 'AT_WEB',
                'cancellationPolicies' => [['amount' => '10.00', 'from' => now()->addDay()->toIso8601String()]],
            ]],
        ]],
    ]]]];
}

function phase14CheckRatePayload(): array
{
    return ['hotel' => ['rooms' => [[
        'code' => 'STD',
        'name' => 'Sandbox Standard Room',
        'rates' => [[
            'rateKey' => 'phase14-rate-checked',
            'rateType' => 'BOOKABLE',
            'rateClass' => 'NOR',
            'net' => '100.00',
            'sellingRate' => '120.00',
            'currency' => 'EGP',
            'boardCode' => 'BB',
            'paymentType' => 'AT_WEB',
            'cancellationPolicies' => [['amount' => '10.00', 'from' => now()->addDay()->toIso8601String()]],
        ]],
    ]]]];
}

function phase14BookingResponse(): array
{
    return ['booking' => [
        'reference' => 'HBX-PHASE14-BOOKING',
        'status' => 'CONFIRMED',
        'totalNet' => '100.00',
        'currency' => 'EGP',
        'creationDate' => now()->toIso8601String(),
    ]];
}
