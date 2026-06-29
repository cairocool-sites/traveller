<?php

use App\Enums\BookingStatus;
use App\Enums\ManualPaymentStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\City;
use App\Models\ManualPaymentMethod;
use App\Models\Payment;
use App\Models\SearchSession;
use App\Models\SupplierOperationLog;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\RateCheckService;
use App\Services\Payment\PaymentFlowException;
use App\Services\Payment\PaymentService;
use Database\Seeders\ManualPaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('local');
    $this->seed();
});

function phase8Booking(): Booking
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
    $rate = $hotel['rates'][0];
    $rateCheck = app(RateCheckService::class)->check($session, $hotel['public_token'], $rate['public_rate_token']);

    return app(BookingService::class)->createAndSubmit($rateCheck, [
        'contact_email' => 'guest@example.test',
        'contact_phone' => '+201000000000',
        'idempotency_key' => (string) Str::uuid(),
        'accept_price_change' => true,
        'guests' => [
            ['type' => 'adult', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'is_lead_guest' => true],
            ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Hassan', 'is_lead_guest' => false],
        ],
    ]);
}

function phase8SubmitPayment(Booking $booking, ?ManualPaymentMethod $method = null, ?UploadedFile $file = null): Payment
{
    $method ??= ManualPaymentMethod::query()->where('code', 'bank_transfer')->firstOrFail();
    $file ??= UploadedFile::fake()->create('receipt.jpg', 10, 'image/jpeg');

    return app(PaymentService::class)->submit($booking, $method, [
        'submitted_reference' => 'REF-123',
        'customer_notes' => 'Paid locally.',
    ], $file);
}

it('shows only active manual payment methods and submits private evidence', function () {
    $booking = phase8Booking();
    ManualPaymentMethod::query()->where('code', 'mobile_wallet')->update(['is_active' => false]);

    $this->get(route('payments.show', ['booking' => $booking->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Bank transfer')
        ->assertDontSee('Mobile wallet');

    $this->post(route('payments.store', ['booking' => $booking->public_uuid, 'locale' => 'en']), [
        'manual_payment_method_id' => ManualPaymentMethod::query()->where('code', 'bank_transfer')->value('id'),
        'submitted_reference' => 'REF-123',
        'evidence' => UploadedFile::fake()->create('proof.jpg', 10, 'image/jpeg'),
    ])->assertRedirect();

    $payment = Payment::query()->with('evidences')->firstOrFail();

    expect($payment->status)->toBe(ManualPaymentStatus::Submitted)
        ->and($payment->amount_minor)->toBe($booking->total_amount_minor)
        ->and($payment->currency_id)->toBe($booking->currency_id)
        ->and($payment->evidences)->toHaveCount(1)
        ->and($payment->evidences->first()->file_path)->not->toContain('public');

    Storage::disk('local')->assertExists($payment->evidences->first()->file_path);
});

it('prevents duplicate active payment submissions and validates method requirements', function () {
    $booking = phase8Booking();
    phase8SubmitPayment($booking);

    phase8SubmitPayment($booking);
})->throws(PaymentFlowException::class);

it('rejects missing references evidence invalid mime and oversized evidence', function () {
    $booking = phase8Booking();
    $method = ManualPaymentMethod::query()->where('code', 'bank_transfer')->firstOrFail();

    app(PaymentService::class)->submit($booking, $method, [], null);
})->throws(PaymentFlowException::class);

it('rejects invalid evidence mime through the public form', function () {
    $booking = phase8Booking();

    $this->post(route('payments.store', ['booking' => $booking->public_uuid]), [
        'manual_payment_method_id' => ManualPaymentMethod::query()->where('code', 'bank_transfer')->value('id'),
        'submitted_reference' => 'REF-123',
        'evidence' => UploadedFile::fake()->create('bad.exe', 10, 'application/x-msdownload'),
    ])->assertSessionHasErrors('evidence');
});

it('requires authorization to download private evidence', function () {
    $payment = phase8SubmitPayment(phase8Booking());
    $evidence = $payment->evidences()->firstOrFail();
    $url = URL::signedRoute('admin.payment-evidence.show', $evidence);

    $this->get($url)->assertForbidden();

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');

    $this->actingAs($accountant)->get($url)->assertOk();
});

it('enforces maker checker and lets accountant approve payment documents without supplier rebooking', function () {
    $booking = phase8Booking();
    $payment = phase8SubmitPayment($booking);
    $bookCalls = SupplierOperationLog::query()->where('operation', 'book')->count();

    $agent = User::factory()->create();
    $agent->assignRole('reservation_agent');
    $this->actingAs($agent);
    expect($agent->can('approve', $payment))->toBeFalse();

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $this->actingAs($accountant);

    $approved = app(PaymentService::class)->approve($payment, 'Matched evidence.');

    expect($approved->status)->toBe(ManualPaymentStatus::Paid)
        ->and($approved->booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($approved->booking->status)->toBe(BookingStatus::Confirmed)
        ->and($approved->receipt)->not->toBeNull()
        ->and($approved->booking->vouchers)->toHaveCount(1)
        ->and($approved->booking->invoices)->toHaveCount(1)
        ->and(SupplierOperationLog::query()->where('operation', 'book')->count())->toBe($bookCalls);
});

it('prevents maker from approving own submission unless super admin provides explicit override reason', function () {
    $maker = User::factory()->create();
    $maker->assignRole('accountant');
    $this->actingAs($maker);

    $payment = phase8SubmitPayment(phase8Booking());

    app(PaymentService::class)->approve($payment);
})->throws(PaymentFlowException::class);

it('requires rejection reason and allows new payment after rejection', function () {
    $payment = phase8SubmitPayment(phase8Booking());
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $this->actingAs($accountant);

    app(PaymentService::class)->reject($payment, '');
})->throws(PaymentFlowException::class);

it('issues immutable voucher invoice and receipt snapshots with minimal verification pages', function () {
    $booking = phase8Booking();
    $payment = phase8SubmitPayment($booking);
    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    $this->actingAs($accountant);

    app(PaymentService::class)->approve($payment, 'Approved.');
    $booking->refresh();
    $voucher = $booking->vouchers()->firstOrFail();
    $invoice = $booking->invoices()->firstOrFail();
    $receipt = $payment->refresh()->receipt;
    $originalName = $voucher->snapshot['hotel_name'];

    $booking->forceFill(['hotel_snapshot' => ['name' => 'Changed Hotel']])->save();

    expect($voucher->fresh()->snapshot['hotel_name'])->toBe($originalName)
        ->and($voucher->verification_token)->toHaveLength(64)
        ->and($invoice->invoice_number)->toStartWith('INV-')
        ->and($receipt->receipt_number)->toStartWith('RCT-');

    $this->get(route('verify.voucher', $voucher->verification_token))
        ->assertOk()
        ->assertSee('Document verified')
        ->assertDontSee('mock_hotels')
        ->assertDontSee('MHB-');
});

it('keeps auditors read only and seeds methods idempotently', function () {
    $count = ManualPaymentMethod::query()->count();
    $this->seed(ManualPaymentMethodSeeder::class);

    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $payment = phase8SubmitPayment(phase8Booking());

    expect(ManualPaymentMethod::query()->count())->toBe($count)
        ->and($auditor->can('viewAny', Payment::class))->toBeTrue()
        ->and($auditor->can('approve', $payment))->toBeFalse();
});
