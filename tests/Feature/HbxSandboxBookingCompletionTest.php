<?php

use App\Enums\BookingStatus;
use App\Enums\RateCheckStatus;
use App\Enums\SupplierStatus;
use App\Models\Booking;
use App\Models\City;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;
use App\Services\Booking\BookingFlowException;
use App\Services\Booking\BookingReconciliationService;
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

    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'phase13-api-key',
        'services.hbx.api_secret' => 'phase13-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
        'services.hbx.sandbox_booking_enabled' => true,
        'travel.public_search.suppliers' => ['hbx_hotels'],
        'travel.public_search.markup_basis_points' => 0,
    ]);

    $this->seed();

    Supplier::query()->where('code', 'hbx_hotels')->update(['status' => SupplierStatus::Active, 'base_url' => null]);
});

it('keeps the sandbox booking guard disabled by default and sends no booking request', function () {
    config(['services.hbx.sandbox_booking_enabled' => false]);
    $rateCheck = phase13RateCheck();

    expect(fn () => app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload()))
        ->toThrow(BookingFlowException::class, 'HBX sandbox booking submission is disabled');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('blocks production hbx booking endpoints before supplier submission', function () {
    $rateCheck = phase13RateCheck();
    config(['services.hbx.base_url' => 'https://api.hotelbeds.com']);

    expect(fn () => app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload()))
        ->toThrow(BookingFlowException::class, 'sandbox endpoint');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('creates a confirmed hbx sandbox booking from trusted server-side rate data', function () {
    $booking = phase13ConfirmedBooking(['total_amount_minor' => 1, 'currency' => 'USD']);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->supplier_booking_reference)->toBe('HBX-BOOK-1')
        ->and($booking->supplier_confirmation_reference)->toBe('HBX-BOOK-1')
        ->and($booking->total_amount_minor)->toBe(12000)
        ->and($booking->net_amount_minor)->toBe(10000)
        ->and($booking->customer_nationality)->toBe('EG')
        ->and($booking->guests)->toHaveCount(2);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/bookings'
        && $request->method() === 'POST'
        && data_get($request->data(), 'clientReference') === $booking->idempotency_key
        && data_get($request->data(), 'rooms.0.rateKey') === 'hbx-rate-checked'
        && data_get($request->data(), 'holder.name') === 'Ali');
});

it('supports recheck rates only after a successful CheckRate', function () {
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload())
        ->push(phase13BookingResponse());

    $rateCheck = phase13RateCheckUsingCurrentFake('RECHECK');

    expect($rateCheck->status)->toBe(RateCheckStatus::Available)
        ->and($rateCheck->supplier_rate_reference)->not->toBe('');

    $booking = app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload());

    expect($booking->status)->toBe(BookingStatus::Confirmed);
});

it('rejects expired missing and invalid rate checks before booking', function () {
    $expired = phase13RateCheck();
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(BookingService::class)->createAndSubmit($expired, phase13BookingPayload()))
        ->toThrow(BookingFlowException::class);

    $failed = $expired->replicate();
    $failed->public_uuid = (string) Str::uuid();
    $failed->expires_at = now()->addMinutes(10);
    $failed->forceFill(['status' => RateCheckStatus::Failed])->save();

    expect(fn () => app(BookingService::class)->createAndSubmit($failed, phase13BookingPayload()))
        ->toThrow(BookingFlowException::class);
});

it('rejects occupancy and child age mismatches', function () {
    $rateCheck = phase13RateCheck('BOOKABLE', ['children' => 1, 'child_ages' => [7]]);

    expect(fn () => app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload([
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
        ],
    ])))->toThrow(InvalidArgumentException::class, 'Guest details must match');

    expect(fn () => app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload([
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
            ['type' => 'child', 'first_name' => 'Omar', 'last_name' => 'Hassan', 'age' => 8, 'is_lead_guest' => false],
        ],
    ])))->toThrow(InvalidArgumentException::class, 'Child ages');
});

it('returns the same booking for duplicate idempotent posts and sends one hbx booking request', function () {
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload())
        ->push(phase13BookingResponse());

    $rateCheck = phase13RateCheckUsingCurrentFake();
    $payload = phase13BookingPayload(['idempotency_key' => 'phase13-duplicate-key']);

    $first = app(BookingService::class)->createAndSubmit($rateCheck, $payload);
    $second = app(BookingService::class)->createAndSubmit($rateCheck, $payload);

    expect($second->id)->toBe($first->id);

    $bookingCalls = Http::recorded(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
    expect($bookingCalls)->toHaveCount(1);
});

it('rejects conflicting idempotency key reuse', function () {
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload())
        ->push(phase13BookingResponse());

    $rateCheck = phase13RateCheckUsingCurrentFake();
    $payload = phase13BookingPayload(['idempotency_key' => 'phase13-conflict-key']);

    app(BookingService::class)->createAndSubmit($rateCheck, $payload);

    expect(fn () => app(BookingService::class)->createAndSubmit($rateCheck, array_merge($payload, ['contact_email' => 'other@example.test'])))
        ->toThrow(InvalidArgumentException::class);
});

it('marks supplier rejections failed', function () {
    $rejected = phase13BookingWithBookingResponse(phase13BookingResponse('REJECTED'));
    expect($rejected->status)->toBe(BookingStatus::SupplierFailed);
});

it('marks timeout outcomes for manual review without retry', function () {
    $call = 0;
    Http::fake(function () use (&$call) {
        $call++;

        return match ($call) {
            1 => Http::response(phase13AvailabilityPayload(), 200),
            2 => Http::response(phase13CheckRatePayload(), 200),
            default => throw new ConnectionException('timeout'),
        };
    });

    $rateCheck = phase13RateCheckUsingCurrentFake();
    $timeout = app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload(['idempotency_key' => 'phase13-timeout']));

    expect($timeout->status)->toBe(BookingStatus::ManualReview)
        ->and($timeout->supplier_booking_reference)->toBe('phase13-timeout')
        ->and($timeout->supplier_response_snapshot['requiresManualReview'])->toBeTrue();

    expect($call)->toBe(3);
});

it('reconciles pending manual review bookings through hbx lookup', function () {
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload())
        ->push(phase13BookingResponse('CONFIRMED'))
        ->push(phase13LookupResponse());

    $rateCheck = phase13RateCheckUsingCurrentFake();
    $booking = app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload());
    $booking->forceFill(['status' => BookingStatus::ManualReview])->save();

    $reconciled = app(BookingReconciliationService::class)->reconcile($booking);

    expect($reconciled->status)->toBe(BookingStatus::Confirmed);
});

it('renders confirmation safely and does not leak credentials or raw rate keys', function () {
    $booking = phase13ConfirmedBooking();

    $this->get(route('bookings.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee($booking->booking_reference)
        ->assertSee('Sandbox test booking')
        ->assertSee('HBX-BOOK-1')
        ->assertDontSee('phase13-api-key')
        ->assertDontSee('phase13-api-secret')
        ->assertDontSee('hbx-rate-checked')
        ->assertDontSee('card number');
});

it('redacts credentials from hbx booking logs and stores no raw secret values', function () {
    $booking = phase13ConfirmedBooking();
    $log = SupplierOperationLog::query()->where('request_url', '/hotel-api/1.0/bookings')->firstOrFail();
    $encoded = json_encode([$log->request_headers, $log->request_payload, $log->response_payload, $booking->supplier_response_snapshot], JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('phase13-api-key')
        ->and($encoded)->not->toContain('phase13-api-secret')
        ->and($encoded)->toContain('[REDACTED]');
});

function phase13Criteria(array $overrides = []): array
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

function phase13SearchSession(array $overrides = []): SearchSession
{
    return app(HotelSearchService::class)->search(phase13Criteria($overrides), 'phase13-session-'.Str::random(6));
}

function phase13RateCheck(string $rateType = 'BOOKABLE', array $criteria = []): RateCheck
{
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload());

    return phase13RateCheckUsingCurrentFake($rateType, $criteria);
}

function phase13RateCheckUsingCurrentFake(string $rateType = 'BOOKABLE', array $criteria = []): RateCheck
{
    $session = phase13SearchSession($criteria);
    $hotel = $session->results_snapshot[0];
    $rate = collect($hotel['rates'])->firstWhere('rate_type', $rateType);

    return app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);
}

function phase13ConfirmedBooking(array $payloadOverrides = []): Booking
{
    return phase13BookingWithBookingResponse(phase13BookingResponse(), $payloadOverrides);
}

function phase13BookingWithBookingResponse(array $response, array $payloadOverrides = []): Booking
{
    Http::fakeSequence()
        ->push(phase13AvailabilityPayload())
        ->push(phase13CheckRatePayload())
        ->push($response);

    $rateCheck = phase13RateCheckUsingCurrentFake();

    return app(BookingService::class)->createAndSubmit($rateCheck, phase13BookingPayload($payloadOverrides));
}

function phase13AvailabilityPayload(): array
{
    return ['hotels' => ['hotels' => [[
        'code' => 1001,
        'name' => 'HBX Cairo Sandbox Hotel',
        'categoryCode' => '5EST',
        'destinationCode' => 'CAI',
        'zoneName' => 'Cairo',
        'rooms' => [[
            'code' => 'STD',
            'name' => 'Standard Room',
            'rates' => [
                ['rateKey' => 'hbx-rate-bookable', 'rateType' => 'BOOKABLE', 'rateClass' => 'NOR', 'net' => '100.00', 'sellingRate' => '120.00', 'currency' => 'EGP', 'boardCode' => 'BB', 'paymentType' => 'AT_WEB', 'cancellationPolicies' => [['amount' => '20.00', 'from' => now()->addDay()->toIso8601String()]]],
                ['rateKey' => 'hbx-rate-recheck', 'rateType' => 'RECHECK', 'rateClass' => 'NOR', 'net' => '100.00', 'sellingRate' => '120.00', 'currency' => 'EGP', 'boardCode' => 'BB', 'paymentType' => 'AT_WEB'],
            ],
        ]],
    ]]]];
}

function phase13CheckRatePayload(): array
{
    return ['hotel' => ['rooms' => [[
        'code' => 'STD',
        'name' => 'Standard Room',
        'rates' => [[
            'rateKey' => 'hbx-rate-checked',
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

function phase13BookingResponse(string $status = 'CONFIRMED'): array
{
    return ['booking' => ['reference' => $status === 'CONFIRMED' ? 'HBX-BOOK-1' : null, 'status' => $status, 'totalNet' => '100.00', 'currency' => 'EGP', 'creationDate' => now()->toIso8601String()]];
}

function phase13LookupResponse(): array
{
    return ['booking' => ['reference' => 'HBX-BOOK-1', 'status' => 'CONFIRMED', 'currency' => 'EGP', 'totalNet' => '100.00', 'hotel' => ['code' => '1001', 'name' => 'HBX Cairo Sandbox Hotel', 'rooms' => []]]];
}

function phase13BookingPayload(array $overrides = []): array
{
    return array_merge([
        'contact_email' => 'guest@example.test',
        'contact_phone' => '+201000000000',
        'customer_nationality' => 'EG',
        'idempotency_key' => (string) Str::uuid(),
        'accept_price_change' => true,
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
        ],
    ], $overrides);
}
