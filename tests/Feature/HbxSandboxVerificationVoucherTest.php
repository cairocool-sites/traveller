<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RateCheckStatus;
use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\Booking;
use App\Models\BookingCertificationEvidence;
use App\Models\City;
use App\Models\Currency;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\ManualPaymentMethod;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use App\Models\SupplierOperationLog;
use App\Models\User;
use App\Services\Booking\BookingReconciliationService;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\Hbx\HbxBookingIdentityService;
use App\Services\Hbx\HbxCertificationEvidenceException;
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

it('blocks live manual booking verification while the sandbox booking guard is disabled', function () {
    $this->artisan('hbx:verify-sandbox-booking')
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
        ->expectsOutputToContain('Supplier: hbx_hotels')
        ->expectsOutputToContain('Local destination:')
        ->expectsOutputToContain('HBX destination code: CAI')
        ->expectsOutputToContain('Number of hotel codes searched: 0')
        ->expectsOutputToContain('Availability result count: 1')
        ->expectsOutputToContain('Availability source: HBX Sandbox')
        ->expectsOutputToContain('CheckRate source: not required')
        ->expectsOutputToContain('Selling total:')
        ->expectsOutputToContain('Dry run complete. No booking request was sent.')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/checkrates'));
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

    $hotel = HbxHotel::query()->where('hotel_code', '1001')->firstOrFail();

    $this->artisan('hbx:verify-sandbox-booking --dry-run --hotel='.$hotel->id)
        ->expectsOutputToContain('Supplier: hbx_hotels')
        ->expectsOutputToContain('HBX destination code: CAI')
        ->expectsOutputToContain('Number of hotel codes searched: 1')
        ->expectsOutputToContain('CheckRate source: not required')
        ->doesntExpectOutputToContain('Mock Cairo Nile Hotel')
        ->expectsOutputToContain('Dry run complete. No booking request was sent.')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/hotels'
        && data_get($request->data(), 'hotels.hotel.0') === 1001
        && data_get($request->data(), 'destination.code') === null);
    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/checkrates');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/hotel-api/1.0/bookings'));
});

it('fails safely when hbx availability is unavailable instead of using mock fallback', function () {
    config([
        'services.hbx.sandbox_booking_enabled' => true,
        'travel.public_search.suppliers' => ['mock_hotels'],
    ]);

    Http::fake(fn () => Http::response(['error' => 'unavailable'], 503));

    $this->artisan('hbx:verify-sandbox-booking --dry-run')
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
        ->assertSee('120.00 USD')
        ->assertDontSee('phase14-api-key')
        ->assertDontSee('phase14-api-secret')
        ->assertDontSee($booking->contact_email)
        ->assertDontSee($booking->contact_phone)
        ->assertDontSee($booking->supplier_rate_reference)
        ->assertDontSee('totalNet');
});

it('allows customer voucher access through opaque booking uuid without sensitive values', function () {
    $booking = phase14ConfirmedBooking();

    $this->get(route('bookings.voucher', $booking->public_uuid))
        ->assertOk()
        ->assertSee('Cairo Cool Travel')
        ->assertSee('Sandbox / Test Booking')
        ->assertSee($booking->booking_reference)
        ->assertSee('Payable through Cairo Cool Travel')
        ->assertSee('Reference: '.$booking->booking_reference)
        ->assertDontSee('phase14-api-key')
        ->assertDontSee('phase14-api-secret')
        ->assertDontSee($booking->contact_email)
        ->assertDontSee($booking->contact_phone)
        ->assertDontSee($booking->supplier_rate_reference);
});

it('blocks customer voucher access while supplier identity is unresolved', function () {
    $booking = phase14ConfirmedBooking();

    $booking->certificationEvidences()->create([
        'operation_type' => 'booking_identity_forensic_audit',
        'local_reference' => $booking->booking_reference,
        'supplier_reference' => $booking->supplier_booking_reference,
        'summary_status' => 'local_mapping_error',
    ]);

    $this->get(route('bookings.voucher', $booking->public_uuid))
        ->assertStatus(409);
});

it('shows hbx rate comments before guest details when supplied', function () {
    $rateCheck = phase14RateCheck([
        'rate_comments' => 'Contract remarks must be reviewed before booking.',
    ]);

    $this->get(route('rate-checks.show', $rateCheck->public_uuid))
        ->assertOk()
        ->assertSee('Important supplier remarks')
        ->assertSee('Contract remarks must be reviewed before booking.');
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

it('retrieves hbx booking detail and stores reconciliation evidence without silent overwrite', function () {
    $booking = phase14LocalConfirmedBooking();
    $originalHotelName = $booking->hotel_snapshot['name'];

    Http::fake(fn () => Http::response(phase14BookingDetailPayload($booking), 200));

    $evidence = app(BookingReconciliationService::class)->audit($booking->refresh());

    expect($evidence->summary_status)->toBe('matched')
        ->and($evidence->field_results['supplier_reference']['classification'])->toBe('matched')
        ->and($evidence->field_results['check_in']['classification'])->toBe('matched')
        ->and($evidence->field_results['check_out']['classification'])->toBe('matched')
        ->and($booking->refresh()->hotel_snapshot['name'])->toBe($originalHotelName);

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.test.hotelbeds.com/hotel-api/1.0/bookings/HBX-PHASE14-BOOKING?language=ENG');
});

it('classifies reconciliation mismatches without overwriting local booking fields', function () {
    $booking = phase14LocalConfirmedBooking();
    $localCheckIn = $booking->check_in->toDateString();
    $payload = phase14BookingDetailPayload($booking);
    $payload['booking']['hotel']['rooms'][0]['rates'][0]['checkIn'] = '2026-07-08';

    Http::fake(fn () => Http::response($payload, 200));

    $evidence = app(BookingReconciliationService::class)->audit($booking->refresh());

    expect($evidence->summary_status)->toBe('manual_review')
        ->and($evidence->field_results['check_in']['classification'])->toBe('mismatched')
        ->and($booking->refresh()->check_in->toDateString())->toBe($localCheckIn);
});

it('does not block booking detail reconciliation only because supplier pricing currency differs from customer currency', function () {
    $booking = phase14LocalConfirmedBooking();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $booking->forceFill(['currency_id' => $usd->id, 'total_amount_minor' => 24948])->save();
    $payload = phase14BookingDetailPayload($booking);
    $payload['booking']['currency'] = 'EUR';
    $payload['booking']['totalNet'] = '230.00';

    Http::fake(fn () => Http::response($payload, 200));

    $evidence = app(BookingReconciliationService::class)->audit($booking->refresh());

    expect($evidence->summary_status)->toBe('matched')
        ->and($evidence->field_results['customer_currency']['classification'])->toBe('customer_pricing')
        ->and($evidence->field_results['supplier_currency']['classification'])->toBe('supplier_pricing')
        ->and($evidence->field_results['supplier_total_amount']['classification'])->toBe('supplier_pricing');
});

it('runs certification evidence without cancellation simulation during identity investigation', function () {
    $booking = phase14LocalConfirmedBooking();
    Http::fakeSequence()->push(phase14BookingDetailPayload($booking));

    $this->artisan('hbx:certification:evidence --booking='.$booking->booking_reference)
        ->expectsOutputToContain('Booking Detail retrieved: yes')
        ->expectsOutputToContain('Supplier reference: HBX-PHASE14-BOOKING')
        ->expectsOutputToContain('Check-in: 2026-07-07')
        ->expectsOutputToContain('Check-out: 2026-07-10')
        ->expectsOutputToContain('Cancellation simulation: not_run')
        ->expectsOutputToContain('Supplier booking remains confirmed: yes')
        ->doesntExpectOutputToContain('phase14-api-key')
        ->doesntExpectOutputToContain('phase14-api-secret')
        ->doesntExpectOutputToContain('Sandbox Tester')
        ->doesntExpectOutputToContain('phase14-rate')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'cancellationFlag=CANCELLATION'));
});

it('runs read-only hbx booking identity audit with bounded booking list candidates', function () {
    $booking = phase14LocalConfirmedBooking();
    Http::fakeSequence()
        ->push(phase14BookingDetailPayload($booking))
        ->push(phase14BookingListPayload($booking, reference: 'HBX-PHASE14-BOOKING'));

    $this->artisan('hbx:booking-identity:audit --booking='.$booking->booking_reference)
        ->expectsOutputToContain('HBX booking identity forensic audit')
        ->expectsOutputToContain('Stored supplier reference: HBX-PHASE14-BOOKING')
        ->expectsOutputToContain('Client reference:')
        ->expectsOutputToContain('Booking List candidates: 1')
        ->expectsOutputToContain('Cause classification: exact_match')
        ->expectsOutputToContain('No booking, cancellation, modification, CheckRate, or production request was sent')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-api/1.0/bookings?')
        && str_contains($request->url(), 'clientReference=')
        && str_contains($request->url(), 'filterType=CREATION')
        && str_contains($request->url(), 'from=1')
        && str_contains($request->url(), 'to=25'));
    Http::assertNotSent(fn ($request): bool => in_array($request->method(), ['POST', 'PUT', 'DELETE'], true));
});

it('accepts original booking client reference evidence when hbx list omits client reference and supplier currency differs', function () {
    $booking = phase14LocalConfirmedBooking();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $clientReference = app(HbxBookingIdentityService::class)->clientReference($booking);

    $booking->forceFill([
        'currency_id' => $usd->id,
        'total_amount_minor' => 24948,
    ])->save();

    SupplierOperationLog::query()->create([
        'supplier_id' => $booking->supplier_id,
        'correlation_id' => $booking->correlation_id,
        'operation' => SupplierOperation::Book,
        'request_method' => 'POST',
        'request_url' => '/hotel-api/1.0/bookings',
        'request_payload' => ['clientReference' => $clientReference],
        'response_status' => 200,
        'response_payload' => phase14BookingDetailPayload($booking),
        'attempt_number' => 1,
        'successful' => true,
        'booking_reference' => $booking->supplier_booking_reference,
        'created_at' => now(),
    ]);

    $detail = phase14BookingDetailPayload($booking);
    $detail['booking']['currency'] = 'EUR';
    $list = phase14BookingListPayload($booking, reference: 'HBX-PHASE14-BOOKING');
    unset($list['bookings']['bookings'][0]['clientReference']);
    $list['bookings']['bookings'][0]['currency'] = 'EUR';

    Http::fakeSequence()
        ->push($detail)
        ->push($list);

    $audit = app(HbxBookingIdentityService::class)->audit($booking->refresh());

    expect($audit['classification'])->toBe('exact_match')
        ->and($audit['original']['client_reference'])->toBe($clientReference)
        ->and($audit['candidates'][0]['client_reference_match'])->toBeFalse();
});

it('classifies hbx detail hotel mismatch as supplier reference unexpected', function () {
    $booking = phase14LocalConfirmedBooking();
    $detail = phase14BookingDetailPayload($booking);
    $detail['booking']['hotel']['code'] = 108694;
    $detail['booking']['hotel']['name'] = 'Indiana Hotel Cairo';

    Http::fakeSequence()
        ->push($detail)
        ->push(['bookings' => ['bookings' => []]]);

    $audit = app(HbxBookingIdentityService::class)->audit($booking);

    expect($audit['classification'])->toBe('supplier_reference_reused_or_unexpected');
});

it('requires exact identity evidence before correcting supplier reference and audits manual correction', function () {
    $booking = phase14LocalConfirmedBooking();
    $booking->forceFill(['supplier_booking_reference' => 'WRONG-REFERENCE', 'supplier_confirmation_reference' => 'WRONG-REFERENCE'])->save();

    Http::fakeSequence()
        ->push(phase14BookingDetailPayload($booking, reference: 'WRONG-REFERENCE'))
        ->push(phase14BookingListPayload($booking, reference: 'HBX-CORRECT-1'))
        ->push(phase14BookingDetailPayload($booking, reference: 'WRONG-REFERENCE'))
        ->push(phase14BookingListPayload($booking, reference: 'HBX-CORRECT-1'));

    $service = app(HbxBookingIdentityService::class);

    expect(fn () => $service->correctSupplierReference($booking, 'NOT-IN-LIST', 'wrong'))
        ->toThrow(HbxCertificationEvidenceException::class);

    $evidence = $service->correctSupplierReference($booking->refresh(), 'HBX-CORRECT-1', 'Exact clientReference and identity match.');

    expect($booking->refresh()->supplier_booking_reference)->toBe('HBX-CORRECT-1')
        ->and($evidence->operation_type)->toBe('supplier_reference_resolution')
        ->and($evidence->sanitized_snapshot['old_reference'])->toBe('WRONG-REFERENCE');
});

it('blocks voucher payment and cancellation while supplier identity is unresolved', function () {
    $booking = phase14LocalConfirmedBooking();
    BookingCertificationEvidence::query()->create([
        'booking_id' => $booking->id,
        'operation_type' => 'booking_identity_forensic_audit',
        'local_reference' => $booking->booking_reference,
        'supplier_reference' => $booking->supplier_booking_reference,
        'summary_status' => 'manual_review',
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->get(route('bookings.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Booking under review')
        ->assertSee($booking->booking_reference)
        ->assertDontSee('Manual payment')
        ->assertDontSee('Cancel booking');

    $this->actingAs($admin)->get(route('admin.bookings.voucher', $booking))->assertStatus(409);
    $this->get(route('payments.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))->assertStatus(409);
    $this->get(route('cancellations.create', ['booking' => $booking->public_uuid, 'locale' => 'en']))->assertStatus(409);
});

it('blocks certification evidence on production endpoints before supplier calls', function () {
    $booking = phase14LocalConfirmedBooking();
    config(['services.hbx.base_url' => 'https://api.hotelbeds.com']);
    Http::fake();

    $this->artisan('hbx:certification:evidence --booking='.$booking->booking_reference)
        ->expectsOutputToContain('blocked outside the sandbox endpoint')
        ->assertFailed();
});

it('renders semantic dates and localized supplier and payment statuses on public pages', function () {
    $booking = phase14LocalConfirmedBooking();

    $this->get(route('bookings.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Check-in')
        ->assertSee('2026-07-07')
        ->assertSee('Check-out')
        ->assertSee('2026-07-10')
        ->assertSee('Confirmed')
        ->assertSee('Payment pending')
        ->assertDontSee('supplier_failed')
        ->assertDontSee('payment_pending');

    app()->setLocale('ar');

    $this->get(route('bookings.show', ['booking' => $booking->public_uuid, 'locale' => 'ar']))
        ->assertOk()
        ->assertSee('تاريخ الوصول')
        ->assertSee('2026-07-07')
        ->assertSee('تاريخ المغادرة')
        ->assertSee('2026-07-10')
        ->assertSee('مؤكد')
        ->assertSee('بانتظار الدفع');
});

it('keeps supplier status unchanged when payment validation fails and shows field errors', function () {
    $booking = phase14LocalConfirmedBooking();
    ManualPaymentMethod::query()->where('code', 'bank_transfer')->update(['requires_reference' => true, 'supports_attachment' => false]);
    $method = ManualPaymentMethod::query()->where('code', 'bank_transfer')->firstOrFail();

    $this->post(route('payments.store', ['booking' => $booking->public_uuid, 'locale' => 'en']), [
        'manual_payment_method_id' => $method->id,
        'submitted_reference' => '',
        'customer_notes' => 'sandbox note',
    ])->assertSessionHasErrors('submitted_reference');

    expect($booking->refresh()->supplier_status)->toBe('confirmed');
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
        ['destination_name' => 'Cairo', 'country_code' => 'EG', 'supplier_active' => true, 'public_enabled' => true, 'is_active' => true, 'synced_at' => now()],
    );

    HbxHotel::query()->updateOrCreate(
        ['supplier_code' => 'hbx_hotels', 'hotel_code' => '1001'],
        ['destination_code' => 'CAI', 'country_code' => 'EG', 'hotel_name' => 'HBX Phase 14 Sandbox Hotel', 'category_code' => '5EST', 'star_rating' => 5, 'supplier_active' => true, 'public_enabled' => true, 'is_active' => true, 'synced_at' => now()],
    );

    SupplierDestinationMapping::query()->updateOrCreate(
        ['local_entity_type' => 'city', 'local_entity_id' => $city->id, 'supplier_code' => 'hbx_hotels', 'supplier_destination_code' => 'CAI'],
        ['status' => 'confirmed', 'confidence' => 100, 'manually_confirmed' => true, 'is_active' => true],
    );
}

function phase14RateCheckUsingCurrentFake(array $overrides = []): RateCheck
{
    $session = phase14SearchSession($overrides);
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][0];
    $rateCheck = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    expect($rateCheck->status)->toBe(RateCheckStatus::Available);

    return $rateCheck;
}

function phase14ConfirmedBooking(array $criteriaOverrides = []): Booking
{
    config(['services.hbx.sandbox_booking_enabled' => true]);
    Http::fakeSequence()
        ->push(phase14AvailabilityPayload())
        ->push(phase14CheckRatePayload())
        ->push(phase14BookingResponse());

    $rateCheck = phase14RateCheckUsingCurrentFake($criteriaOverrides);

    return app(BookingService::class)->createAndSubmit($rateCheck, phase14BookingPayload());
}

function phase14LocalConfirmedBooking(): Booking
{
    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $currency = Currency::query()->where('code', 'EGP')->firstOrFail();
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();
    $occupancy = [['adults' => 2, 'children' => 0, 'child_ages' => []]];
    $roomSnapshot = [
        'room_name' => 'Sandbox Standard Room',
        'board_basis' => 'bed_and_breakfast',
        'supplier_total' => ['minor_amount' => 12000, 'currency' => 'EGP'],
        'cancellation_summary' => 'Penalty may apply after supplier deadline.',
    ];

    $session = SearchSession::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'destination_type' => 'city',
        'destination_id' => $city->id,
        'destination_label' => 'Cairo',
        'check_in' => '2026-07-07',
        'check_out' => '2026-07-10',
        'occupancy' => $occupancy,
        'nationality' => 'EG',
        'residency_country' => 'EG',
        'currency' => 'EGP',
        'locale' => 'en',
        'correlation_id' => (string) Str::uuid(),
        'criteria_snapshot' => [],
        'results_snapshot' => [['name' => 'HBX Phase 14 Sandbox Hotel']],
        'warnings' => [],
        'expires_at' => now()->addHour(),
    ]);

    $rateCheck = RateCheck::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'search_session_id' => $session->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'status' => RateCheckStatus::Available,
        'supplier_hotel_reference' => '1401',
        'supplier_rate_reference' => 'phase14-rate-checked',
        'supplier_room_reference' => 'STD',
        'original_amount_minor' => 12000,
        'checked_amount_minor' => 12000,
        'price_changed' => false,
        'cancellation_policy_snapshot' => [['amount' => ['minor_amount' => 1000, 'currency' => 'EGP']]],
        'room_snapshot' => $roomSnapshot,
        'occupancy_snapshot' => $occupancy,
        'supplier_reference_snapshot' => ['supplier' => 'hbx_hotels'],
        'correlation_id' => (string) Str::uuid(),
        'checked_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $booking = Booking::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'booking_reference' => 'CCT-2026-422M23IT',
        'search_session_id' => $session->id,
        'rate_check_id' => $rateCheck->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'status' => BookingStatus::Confirmed,
        'payment_status' => PaymentStatus::Pending,
        'locale' => 'en',
        'check_in' => '2026-07-07',
        'check_out' => '2026-07-10',
        'rooms_count' => 1,
        'adults_count' => 2,
        'children_count' => 0,
        'supplier_booking_reference' => 'HBX-PHASE14-BOOKING',
        'supplier_confirmation_reference' => 'HBX-PHASE14-BOOKING',
        'supplier_status' => 'confirmed',
        'total_amount_minor' => 12000,
        'net_amount_minor' => 10000,
        'taxes_amount_minor' => null,
        'fees_amount_minor' => null,
        'cancellation_policy_snapshot' => [['amount' => ['minor_amount' => 1000, 'currency' => 'EGP']]],
        'hotel_snapshot' => ['name' => 'HBX Phase 14 Sandbox Hotel', 'location' => 'Cairo', 'star_rating' => 5],
        'room_snapshot' => $roomSnapshot,
        'occupancy_snapshot' => $occupancy,
        'supplier_response_snapshot' => ['reference_present' => true],
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => (string) Str::uuid(),
        'idempotency_payload_hash' => hash('sha256', 'phase14-local'),
        'contact_email' => 'sandbox.guest@example.test',
        'contact_phone' => '+200000000000',
        'customer_nationality' => 'EG',
        'confirmed_at' => now(),
    ]);

    $room = $booking->rooms()->create([
        'room_index' => 1,
        'room_name' => 'Sandbox Standard Room',
        'board_basis' => 'bed_and_breakfast',
        'adults' => 2,
        'children' => 0,
        'child_ages' => [],
        'amount_minor' => 12000,
        'cancellation_policy_snapshot' => [['amount' => ['minor_amount' => 1000, 'currency' => 'EGP']]],
        'supplier_room_reference' => 'STD',
    ]);

    $booking->guests()->createMany([
        ['booking_room_id' => $room->id, 'type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Tester', 'is_lead_guest' => true, 'sort_order' => 1],
        ['booking_room_id' => $room->id, 'type' => 'adult', 'first_name' => 'Sandbox', 'last_name' => 'Traveler', 'is_lead_guest' => false, 'sort_order' => 2],
    ]);

    return $booking->refresh();
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

function phase14RateCheck(array $roomSnapshot = []): RateCheck
{
    $currency = Currency::query()->where('code', 'USD')->firstOrFail();
    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();

    $session = SearchSession::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'locale' => 'en',
        'destination_type' => 'hbx_destination',
        'destination_id' => 1,
        'destination_label' => 'Cairo',
        'check_in' => now()->addDays(10)->toDateString(),
        'check_out' => now()->addDays(12)->toDateString(),
        'occupancy' => [['adults' => 2, 'children' => 0, 'child_ages' => []]],
        'currency' => 'USD',
        'correlation_id' => (string) Str::uuid(),
        'criteria_snapshot' => [],
        'results_snapshot' => [],
        'expires_at' => now()->addMinutes(20),
    ]);

    return RateCheck::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'search_session_id' => $session->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'status' => RateCheckStatus::Available,
        'supplier_hotel_reference' => '1401',
        'supplier_rate_reference' => 'phase14-rate-checked',
        'supplier_room_reference' => 'STD',
        'original_amount_minor' => 12000,
        'checked_amount_minor' => 12000,
        'price_changed' => false,
        'cancellation_policy_snapshot' => [],
        'room_snapshot' => array_merge([
            'room_name' => 'Sandbox Standard Room',
            'board_basis' => 'bed_and_breakfast',
            'cancellation_summary' => 'Free cancellation until supplier deadline.',
        ], $roomSnapshot),
        'occupancy_snapshot' => [['adults' => 2, 'children' => 0, 'child_ages' => []]],
        'supplier_reference_snapshot' => [],
        'correlation_id' => (string) Str::uuid(),
        'checked_at' => now(),
        'expires_at' => now()->addMinutes(20),
    ]);
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

function phase14BookingDetailPayload(Booking $booking, ?string $reference = null): array
{
    return ['booking' => [
        'reference' => $reference ?? 'HBX-PHASE14-BOOKING',
        'status' => 'CONFIRMED',
        'totalNet' => '120.00',
        'currency' => 'EGP',
        'creationDate' => now()->toIso8601String(),
        'holder' => ['name' => 'Sandbox', 'surname' => 'Tester'],
        'hotel' => [
            'code' => 1401,
            'name' => 'HBX Phase 14 Sandbox Hotel',
            'categoryCode' => '5EST',
            'destinationCode' => 'CAI',
            'rooms' => [[
                'code' => 'STD',
                'name' => 'Sandbox Standard Room',
                'paxes' => [
                    ['type' => 'AD'],
                    ['type' => 'AD'],
                ],
                'rates' => [[
                    'boardCode' => 'BB',
                    'checkIn' => $booking->check_in->toDateString(),
                    'checkOut' => $booking->check_out->toDateString(),
                    'cancellationPolicies' => [['amount' => '10.00', 'from' => now()->addDay()->toIso8601String()]],
                    'rateComments' => 'Sandbox remarks available.',
                ]],
            ]],
        ],
    ]];
}

function phase14BookingListPayload(Booking $booking, string $reference): array
{
    return ['bookings' => ['bookings' => [[
        'reference' => $reference,
        'clientReference' => Str::of($booking->idempotency_key)->replaceMatches('/[^A-Za-z0-9_-]/', '')->limit(20, '')->toString(),
        'creationDate' => $booking->created_at->toDateString(),
        'status' => 'CONFIRMED',
        'hotel' => [
            'code' => 1401,
            'name' => 'HBX Phase 14 Sandbox Hotel',
            'destinationCode' => 'CAI',
            'checkIn' => $booking->check_in->toDateString(),
            'checkOut' => $booking->check_out->toDateString(),
            'rooms' => [[
                'code' => 'STD',
                'paxes' => [['type' => 'AD'], ['type' => 'AD']],
                'rates' => [['boardCode' => 'BB', 'rooms' => 1]],
            ]],
        ],
        'currency' => 'EGP',
        'totalSellingRate' => '120.00',
    ]]]];
}

function phase14CancellationSimulationPayload(Booking $booking): array
{
    return ['booking' => [
        'reference' => $booking->supplier_booking_reference,
        'status' => 'CONFIRMED',
        'currency' => 'EGP',
        'cancellationAmount' => '10.00',
    ]];
}
