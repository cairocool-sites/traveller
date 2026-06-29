<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RateCheckStatus;
use App\Models\Booking;
use App\Models\Currency;
use App\Models\OperationalHeartbeat;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Supplier\PayloadSanitizer;
use App\Support\Operations\SystemHealthService;
use Database\Seeders\AdminFoundationSeeder;
use Database\Seeders\CoreReferenceDataSeeder;
use Database\Seeders\SupplierFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminFoundationSeeder::class);
});

it('returns minimal liveness health without sensitive details', function () {
    $this->getJson('/health/live')
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('handles unavailable readiness dependencies safely', function () {
    $this->app->instance(SystemHealthService::class, new class extends SystemHealthService
    {
        public function readiness(): array
        {
            return ['ok' => false, 'checks' => []];
        }
    });

    $this->getJson('/health/ready')
        ->assertStatus(503)
        ->assertJsonPath('status', 'unavailable')
        ->assertJsonMissingPath('checks')
        ->assertJsonMissing(['APP_KEY', base_path()]);
});

it('detects unsafe production environment settings without printing secrets', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['app.key' => null, 'app.debug' => true, 'app.url' => 'http://example.test']);

    $this->artisan('app:check-environment', ['--json' => true])
        ->assertFailed()
        ->doesntExpectOutputToContain('password')
        ->doesntExpectOutputToContain('secret');
});

it('records scheduler heartbeat and detects stale scheduler state', function () {
    $this->artisan('ops:scheduler-heartbeat')->assertSuccessful();

    expect(OperationalHeartbeat::query()->where('key', 'scheduler')->exists())->toBeTrue();

    OperationalHeartbeat::query()->where('key', 'scheduler')->update(['last_seen_at' => now()->subMinutes(10)]);

    $summary = app(SystemHealthService::class)->adminSummary();

    expect($summary['scheduler']['ok'])->toBeFalse();
});

it('protects the system health admin page by permission', function () {
    $guestAdmin = User::factory()->create();
    $guestAdmin->assignRole('reservation_agent');

    $this->actingAs($guestAdmin)
        ->get('/admin/system-health')
        ->assertForbidden();

    $operator = User::factory()->create();
    $operator->assignRole('operations_admin');

    $this->actingAs($operator)
        ->get('/admin/system-health')
        ->assertOk();
});

it('adds security headers and correlation IDs to public responses', function () {
    $correlationId = (string) Str::uuid();

    $this->withHeader('X-Correlation-ID', $correlationId)
        ->get('/')
        ->assertOk()
        ->assertHeader('X-Correlation-ID', $correlationId)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('registers named rate limiters for critical public routes', function () {
    foreach (array_keys(config('travel.rate_limits')) as $name) {
        expect(RateLimiter::limiter($name))->not->toBeNull();
    }
});

it('keeps cleanup dry-run non destructive and removes eligible transient records', function () {
    [$searchSession, $rateCheck, $booking] = createExpiredTransientRecords();

    $this->artisan('ops:cleanup', ['--dry-run' => true])->assertSuccessful();

    expect(SearchSession::query()->whereKey($searchSession->id)->exists())->toBeTrue()
        ->and(RateCheck::query()->whereKey($rateCheck->id)->exists())->toBeTrue()
        ->and(Booking::query()->whereKey($booking->id)->exists())->toBeTrue();

    $this->artisan('ops:cleanup')->assertSuccessful();

    expect(SearchSession::query()->whereKey($searchSession->id)->exists())->toBeFalse()
        ->and(RateCheck::query()->whereKey($rateCheck->id)->exists())->toBeFalse()
        ->and(Booking::query()->whereKey($booking->id)->exists())->toBeFalse();
});

it('does not delete confirmed booking records during cleanup', function () {
    [, , $booking] = createExpiredTransientRecords(BookingStatus::Confirmed);

    $this->artisan('ops:cleanup')->assertSuccessful();

    expect(Booking::query()->whereKey($booking->id)->exists())->toBeTrue();
});

it('redacts sensitive log payload values recursively', function () {
    $clean = app(PayloadSanitizer::class)->sanitize([
        'Authorization' => 'Bearer token',
        'nested' => [
            'password' => 'secret',
            'guest_email' => 'customer@example.test',
            'safe' => 'kept',
        ],
    ]);

    expect($clean['Authorization'])->toBe('[REDACTED]')
        ->and($clean['nested']['password'])->toBe('[REDACTED]')
        ->and($clean['nested']['guest_email'])->toBe('[REDACTED]')
        ->and($clean['nested']['safe'])->toBe('kept');
});

it('keeps the permission seeder idempotent with operations permissions', function () {
    $this->seed(AdminFoundationSeeder::class);

    $operator = User::factory()->create();
    $operator->assignRole('operations_admin');

    expect($operator->can('view_system_health'))->toBeTrue()
        ->and($operator->can('manage_operational_tasks'))->toBeFalse();
});

function createExpiredTransientRecords(BookingStatus $bookingStatus = BookingStatus::Draft): array
{
    test()->seed(CoreReferenceDataSeeder::class);
    test()->seed(SupplierFoundationSeeder::class);

    $currency = Currency::query()->where('code', 'EGP')->firstOrFail();
    $supplier = Supplier::query()->where('code', 'mock_hotels')->firstOrFail();

    $searchSession = SearchSession::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'destination_type' => 'city',
        'destination_id' => 1,
        'destination_label' => 'Cairo',
        'check_in' => now()->addDay()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'occupancy' => [['adults' => 1, 'children' => 0, 'child_ages' => []]],
        'currency' => 'EGP',
        'locale' => 'ar',
        'correlation_id' => (string) Str::uuid(),
        'criteria_snapshot' => [],
        'expires_at' => now()->subHour(),
    ]);

    $rateCheck = RateCheck::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'search_session_id' => $searchSession->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'status' => RateCheckStatus::Available,
        'supplier_hotel_reference' => 'mock-hotel',
        'supplier_rate_reference' => 'mock-rate',
        'original_amount_minor' => 10000,
        'room_snapshot' => [],
        'occupancy_snapshot' => [],
        'correlation_id' => (string) Str::uuid(),
        'expires_at' => now()->subHour(),
    ]);

    $booking = Booking::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'booking_reference' => 'BKG-'.Str::upper(Str::random(8)),
        'search_session_id' => $searchSession->id,
        'rate_check_id' => $rateCheck->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'status' => $bookingStatus,
        'payment_status' => PaymentStatus::Pending,
        'check_in' => now()->addDay()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'rooms_count' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'total_amount_minor' => 10000,
        'hotel_snapshot' => [],
        'room_snapshot' => [],
        'occupancy_snapshot' => [],
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => (string) Str::uuid(),
        'idempotency_payload_hash' => hash('sha256', Str::random()),
        'expires_at' => now()->subHour(),
    ]);

    DB::table('supplier_operation_logs')->insert([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => 'search',
        'attempt_number' => 1,
        'successful' => true,
        'created_at' => now()->subDays(100),
    ]);

    return [$searchSession, $rateCheck, $booking];
}
