<?php

use App\Enums\BookingStatus;
use App\Enums\RateCheckStatus;
use App\Models\Booking;
use App\Models\City;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\User;
use App\Services\Booking\BookingFlowException;
use App\Services\Booking\BookingReconciliationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
});

function phase7SearchCriteria(array $overrides = []): array
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

function phase7SearchSession(array $overrides = []): SearchSession
{
    test()->get(route('hotels.search', phase7SearchCriteria($overrides)))->assertOk();

    return SearchSession::query()->firstOrFail();
}

function phase7RateCheck(array $metadata = []): RateCheck
{
    $session = phase7SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];

    return app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token'], $metadata);
}

function phase7BookingPayload(array $overrides = []): array
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

it('stores trusted rate checks without exposing supplier identifiers publicly', function () {
    $session = phase7SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];

    expect($rate)->toHaveKeys(['public_rate_token', 'supplier_rate_key', 'supplier_room_id']);

    $this->get(route('hotels.show', ['hotel' => $hotel['public_token'], 'search' => $session->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Check rate')
        ->assertDontSee($hotel['supplier_hotel_id'])
        ->assertDontSee($rate['supplier_rate_key']);

    $rateCheck = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($rateCheck->status)->toBe(RateCheckStatus::Available)
        ->and($rateCheck->checked_amount_minor)->toBe(250000)
        ->and($rateCheck->supplier_rate_reference)->toContain('|checked');
});

it('creates a confirmed mock supplier booking with rooms guests and status history', function () {
    $booking = app(BookingService::class)->createAndSubmit(phase7RateCheck(), phase7BookingPayload());

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->public_uuid)->not->toBe((string) $booking->id)
        ->and($booking->booking_reference)->toStartWith('CCT-')
        ->and($booking->supplier_booking_reference)->toStartWith('MHB-')
        ->and($booking->rooms)->toHaveCount(1)
        ->and($booking->guests)->toHaveCount(2)
        ->and($booking->statusHistories)->toHaveCount(4);
});

it('returns the same booking for duplicate idempotent submission and rejects conflicting reuse', function () {
    $rateCheck = phase7RateCheck();
    $payload = phase7BookingPayload(['idempotency_key' => 'phase7-duplicate-key']);

    $first = app(BookingService::class)->createAndSubmit($rateCheck, $payload);
    $second = app(BookingService::class)->createAndSubmit($rateCheck, $payload);

    expect($second->id)->toBe($first->id);

    app(BookingService::class)->createAndSubmit($rateCheck, array_merge($payload, ['contact_email' => 'other@example.test']));
})->throws(InvalidArgumentException::class);

it('requires price change acceptance and exact guest occupancy', function () {
    $priceChanged = phase7RateCheck(['scenario' => 'price_changed']);

    app(BookingService::class)->createAndSubmit($priceChanged, phase7BookingPayload(['accept_price_change' => false]));
})->throws(BookingFlowException::class);

it('rejects guest details that do not match occupancy', function () {
    app(BookingService::class)->createAndSubmit(phase7RateCheck(), phase7BookingPayload([
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
        ],
    ]));
})->throws(InvalidArgumentException::class);

it('places uncertain supplier bookings into manual review and reconciles them by lookup', function () {
    $booking = app(BookingService::class)->createAndSubmit(phase7RateCheck(), phase7BookingPayload(['scenario' => 'uncertain']));

    expect($booking->status)->toBe(BookingStatus::ManualReview);

    $reconciled = app(BookingReconciliationService::class)->reconcile($booking);

    expect($reconciled->status)->toBe(BookingStatus::Confirmed);
});

it('renders the public check rate and confirmation flow', function () {
    $session = phase7SearchSession();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];

    $this->post(route('rate-checks.store', ['locale' => 'en']), [
        'search' => $session->public_uuid,
        'hotel' => $hotel['public_token'],
        'rate' => $rate['public_rate_token'],
    ])->assertRedirect();

    $rateCheck = RateCheck::query()->firstOrFail();

    $this->get(route('rate-checks.show', ['rateCheck' => $rateCheck->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Guest details')
        ->assertDontSee($rate['supplier_rate_key']);

    $booking = app(BookingService::class)->createAndSubmit($rateCheck, phase7BookingPayload());

    $this->get(route('bookings.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee($booking->booking_reference)
        ->assertDontSee($booking->supplier_booking_reference);
});

it('adds booking permissions without giving auditors mutation rights', function () {
    $manager = User::factory()->create();
    $manager->assignRole('reservation_manager');

    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $booking = app(BookingService::class)->createAndSubmit(phase7RateCheck(), phase7BookingPayload());

    expect($manager->can('viewAny', Booking::class))->toBeTrue()
        ->and($manager->can('reconcile', $booking))->toBeTrue()
        ->and($auditor->can('viewAny', Booking::class))->toBeTrue()
        ->and($auditor->can('update', $booking))->toBeFalse()
        ->and($auditor->can('reconcile', $booking))->toBeFalse();
});
