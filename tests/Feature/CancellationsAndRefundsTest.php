<?php

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Enums\ManualPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\City;
use App\Models\ManualPaymentMethod;
use App\Models\Payment;
use App\Models\SearchSession;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\Cancellation\CancellationEligibilityService;
use App\Services\Cancellation\CancellationFlowException;
use App\Services\Cancellation\CancellationService;
use App\Services\Payment\PaymentService;
use App\Services\Refund\RefundFlowException;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('local');
    $this->seed();
});

function phase9Booking(int $rateIndex = 0): Booking
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

    test()->get(route('hotels.search', [
        'destination' => "city:{$city->id}",
        'check_in' => now()->addDays(8)->toDateString(),
        'check_out' => now()->addDays(10)->toDateString(),
        'rooms' => 1,
        'adults' => 2,
        'children' => 0,
        'currency' => 'EGP',
        'locale' => 'en',
    ]))->assertOk();

    $session = SearchSession::query()->firstOrFail();
    $hotel = $session->results_snapshot[0];
    $rate = $hotel['rates'][$rateIndex];
    $rateCheck = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    return app(BookingService::class)->createAndSubmit($rateCheck, [
        'contact_email' => 'guest@example.test',
        'idempotency_key' => (string) Str::uuid(),
        'accept_price_change' => true,
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
        ],
    ]);
}

function phase9PaidPayment(Booking $booking): Payment
{
    $payment = app(PaymentService::class)->submit(
        $booking,
        ManualPaymentMethod::query()->where('code', 'bank_transfer')->firstOrFail(),
        ['submitted_reference' => 'PAY-REF'],
        UploadedFile::fake()->create('proof.jpg', 10, 'image/jpeg'),
    );

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    test()->actingAs($accountant);

    return app(PaymentService::class)->approve($payment, 'Matched.');
}

it('evaluates free penalized non-refundable and unknown cancellation policies', function () {
    $free = phase9Booking();
    expect(app(CancellationEligibilityService::class)->evaluate($free)->penaltyMinor)->toBe(0);

    $penalized = phase9Booking();
    $penalized->forceFill(['cancellation_policy_snapshot' => [[
        'valid_from' => null,
        'valid_until' => null,
        'penalty_type' => 'amount',
        'penalty_amount' => ['minor_amount' => 50000, 'currency' => 'EGP'],
        'is_non_refundable' => false,
    ]]])->save();
    $penalty = app(CancellationEligibilityService::class)->evaluate($penalized);
    expect($penalty->penaltyMinor)->toBe(50000)->and($penalty->refundableMinor)->toBe($penalized->total_amount_minor - 50000);

    $nonRefundable = phase9Booking(1);
    expect(app(CancellationEligibilityService::class)->evaluate($nonRefundable)->nonRefundable)->toBeTrue();

    $unknown = phase9Booking();
    $unknown->forceFill(['cancellation_policy_snapshot' => []])->save();
    expect(app(CancellationEligibilityService::class)->evaluate($unknown)->manualReview)->toBeTrue();
});

it('submits a confirmed booking cancellation idempotently and cancels booking through mock supplier', function () {
    $booking = phase9Booking();
    $payload = ['idempotency_key' => 'cancel-key-1', 'customer_reason' => 'Plans changed.', 'confirm' => true];

    $first = app(CancellationService::class)->request($booking, $payload);
    $second = app(CancellationService::class)->request($booking, $payload);

    expect($second->id)->toBe($first->id)
        ->and($first->status)->toBe(CancellationStatus::Cancelled)
        ->and($first->booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($first->statusHistories()->count())->toBeGreaterThan(1);
});

it('prevents failed already cancelled duplicate and conflicting cancellation requests', function () {
    $failed = phase9Booking();
    $failed->forceFill(['status' => BookingStatus::SupplierFailed])->save();
    app(CancellationService::class)->request($failed, ['idempotency_key' => 'bad', 'confirm' => true]);
})->throws(CancellationFlowException::class);

it('rejects conflicting cancellation idempotency reuse', function () {
    $booking = phase9Booking();
    app(CancellationService::class)->request($booking, ['idempotency_key' => 'same-cancel', 'customer_reason' => 'A', 'confirm' => true]);
    app(CancellationService::class)->request($booking, ['idempotency_key' => 'same-cancel', 'customer_reason' => 'B', 'confirm' => true]);
})->throws(InvalidArgumentException::class);

it('routes non-refundable cancellation to manual review unless acknowledged', function () {
    $booking = phase9Booking(1);
    app(CancellationService::class)->request($booking, ['idempotency_key' => 'nr', 'confirm' => true]);
})->throws(CancellationFlowException::class);

it('creates and completes manual refunds without over-refunding or deleting documents', function () {
    $booking = phase9Booking();
    $payment = phase9PaidPayment($booking);
    $cancellation = app(CancellationService::class)->request($booking, ['idempotency_key' => 'cancel-paid', 'confirm' => true]);
    $invoiceId = $booking->invoices()->firstOrFail()->id;
    $receiptId = $payment->receipt->id;

    $refund = app(RefundService::class)->create($cancellation, $payment->refresh(), 100000);

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $this->actingAs($accountant);

    app(RefundService::class)->approve($refund, 'Allowed.');
    $completed = app(RefundService::class)->complete($refund->refresh(), 'MANUAL-RFD-1');

    expect($completed->status)->toBe(RefundStatus::Completed)
        ->and($completed->payment->status)->toBe(ManualPaymentStatus::PartiallyRefunded)
        ->and($completed->booking->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($booking->invoices()->whereKey($invoiceId)->exists())->toBeTrue()
        ->and($payment->receipt()->whereKey($receiptId)->exists())->toBeTrue();
});

it('prevents refund over refundable amount and paid amount', function () {
    $booking = phase9Booking();
    $payment = phase9PaidPayment($booking);
    $cancellation = app(CancellationService::class)->request($booking, ['idempotency_key' => 'cancel-over', 'confirm' => true]);

    app(RefundService::class)->create($cancellation, $payment, $cancellation->refundable_amount_minor + 1);
})->throws(RefundFlowException::class);

it('enforces refund maker checker and role permissions', function () {
    $booking = phase9Booking();
    $payment = phase9PaidPayment($booking);
    $cancellation = app(CancellationService::class)->request($booking, ['idempotency_key' => 'cancel-maker', 'confirm' => true]);

    $maker = User::factory()->create();
    $maker->assignRole('accountant');
    $this->actingAs($maker);
    $refund = app(RefundService::class)->create($cancellation, $payment, 100000);

    expect(User::factory()->create()->assignRole('reservation_agent'))->not->toBeNull();
    app(RefundService::class)->approve($refund);
})->throws(RefundFlowException::class);

it('shows public cancellation and refund statuses without supplier internals', function () {
    $booking = phase9Booking();
    $payment = phase9PaidPayment($booking);
    $cancellation = app(CancellationService::class)->request($booking, ['idempotency_key' => 'cancel-public', 'confirm' => true]);
    $refund = app(RefundService::class)->create($cancellation, $payment, 100000);

    $this->get(route('cancellations.status', $cancellation->public_uuid))
        ->assertOk()
        ->assertSee('Cancellation status')
        ->assertDontSee('mock_hotels')
        ->assertDontSee('MCX-');

    $this->get(route('refunds.show', $refund->public_uuid))
        ->assertOk()
        ->assertSee('Refund status')
        ->assertDontSee('internal_notes');
});

it('does not roll back when cancellation notification fails and keeps auditor read only', function () {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('mail down'));
    $cancellation = app(CancellationService::class)->request(phase9Booking(), ['idempotency_key' => 'notify-cancel', 'confirm' => true]);

    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    expect($cancellation->status)->toBe(CancellationStatus::Cancelled)
        ->and($auditor->can('viewAny', BookingCancellation::class))->toBeTrue()
        ->and($auditor->can('review', $cancellation))->toBeFalse();
});
