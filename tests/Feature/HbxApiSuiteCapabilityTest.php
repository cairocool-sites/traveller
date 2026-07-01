<?php

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use App\Models\HbxApiCapability;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;
use App\Services\Supplier\Hbx\HbxApiCapabilityRegistry;
use Database\Seeders\SupplierFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('seeds the hbx api suite capability matrix without secrets', function () {
    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'suite-api-key',
        'services.hbx.api_secret' => 'suite-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
    ]);

    $this->seed(SupplierFoundationSeeder::class);

    expect(HbxApiCapability::query()->count())->toBeGreaterThanOrEqual(40)
        ->and(HbxApiCapability::query()->where('capability_code', 'booking_availability')->value('implemented'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'payment_data_support')->value('public_enabled'))->toBeFalse()
        ->and(HbxApiCapability::query()->where('capability_code', 'cache_full')->value('implemented'))->toBeFalse()
        ->and(HbxApiCapability::query()->where('capability_code', 'content_master_data')->value('implemented'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'content_hotel_images')->value('implemented'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'content_image_types')->value('implemented'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'content_facilities')->value('implemented'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'certification_readiness')->value('implemented'))->toBeTrue();

    $encoded = HbxApiCapability::query()->get()->toJson();

    expect($encoded)->not->toContain('suite-api-key')
        ->and($encoded)->not->toContain('suite-api-secret')
        ->and($encoded)->not->toContain('X-Signature')
        ->and($encoded)->not->toContain('rateKey');
});

it('prints hbx certification readiness without making supplier requests', function () {
    Http::preventStrayRequests();

    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'cert-api-key',
        'services.hbx.api_secret' => 'cert-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
    ]);

    $this->seed(SupplierFoundationSeeder::class);

    $this->artisan('hbx:certification:readiness')
        ->expectsOutputToContain('HBX certification readiness checklist')
        ->expectsOutputToContain('No supplier request was sent by this command.')
        ->expectsOutputToContain('No booking, modification, cancellation, or production request was sent.')
        ->expectsOutputToContain('Technical')
        ->expectsOutputToContain('Workflow')
        ->expectsOutputToContain('Voucher')
        ->expectsOutputToContain('Live environment')
        ->assertSuccessful();

    $output = Artisan::output();

    expect($output)->not->toContain('cert-api-key')
        ->and($output)->not->toContain('cert-api-secret')
        ->and($output)->not->toContain('X-Signature:')
        ->and(Route::has('bookings.voucher'))->toBeTrue();
});

it('prints hbx capability status without making supplier requests', function () {
    Http::preventStrayRequests();

    $this->seed(SupplierFoundationSeeder::class);

    $this->artisan('hbx:api-suite:status')
        ->expectsOutputToContain('HBX Hotels API Suite capability matrix')
        ->expectsOutputToContain('No supplier request was sent by this command.')
        ->expectsOutputToContain('booking_availability')
        ->expectsOutputToContain('content_hotel_images')
        ->expectsOutputToContain('content_image_types')
        ->expectsOutputToContain('payment_data_support')
        ->assertSuccessful();
});

it('derives sandbox-tested and failure status from sanitized operation logs', function () {
    config(['services.hbx.enabled' => true, 'services.hbx.api_key' => 'key', 'services.hbx.api_secret' => 'secret']);
    $this->seed(SupplierFoundationSeeder::class);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();

    SupplierOperationLog::query()->create([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => SupplierOperation::Search,
        'request_method' => 'POST',
        'request_url' => '/hotel-api/1.0/hotels',
        'successful' => true,
        'created_at' => now(),
    ]);

    SupplierOperationLog::query()->create([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => SupplierOperation::HotelDetails,
        'request_method' => 'GET',
        'request_url' => '/hotel-content-api/1.0/locations/countries',
        'successful' => false,
        'error_type' => SupplierErrorType::RateLimit,
        'error_message' => 'Rate limit exceeded without any credential value.',
        'created_at' => now(),
    ]);

    app(HbxApiCapabilityRegistry::class)->sync();

    $availability = HbxApiCapability::query()->where('capability_code', 'booking_availability')->firstOrFail();
    $countries = HbxApiCapability::query()->where('capability_code', 'content_countries')->firstOrFail();

    expect($availability->credential_access_confirmed)->toBeTrue()
        ->and($availability->sandbox_tested)->toBeTrue()
        ->and($countries->credential_access_confirmed)->toBeFalse()
        ->and($countries->last_sanitized_failure)->toContain('Rate limit exceeded')
        ->and($countries->last_sanitized_failure)->not->toContain('secret');
});

it('keeps credential access confirmed after a prior successful capability call even if the latest call failed', function () {
    config(['services.hbx.enabled' => true, 'services.hbx.api_key' => 'key', 'services.hbx.api_secret' => 'secret']);
    $this->seed(SupplierFoundationSeeder::class);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();

    SupplierOperationLog::query()->create([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => SupplierOperation::Search,
        'request_method' => 'POST',
        'request_url' => '/hotel-api/1.0/hotels',
        'successful' => true,
        'created_at' => now()->subMinute(),
    ]);

    SupplierOperationLog::query()->create([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => SupplierOperation::Search,
        'request_method' => 'POST',
        'request_url' => '/hotel-api/1.0/hotels',
        'successful' => false,
        'error_type' => SupplierErrorType::InvalidResponse,
        'error_message' => 'Sanitized supplier failure after a valid call.',
        'created_at' => now(),
    ]);

    app(HbxApiCapabilityRegistry::class)->sync();

    $availability = HbxApiCapability::query()->where('capability_code', 'booking_availability')->firstOrFail();

    expect($availability->credential_access_confirmed)->toBeTrue()
        ->and($availability->sandbox_tested)->toBeTrue()
        ->and($availability->last_successful_call_at)->not->toBeNull()
        ->and($availability->last_sanitized_failure)->toContain('Sanitized supplier failure');
});

it('does not infer credential access for non-callable gated capabilities from unrelated successful logs', function () {
    config(['services.hbx.enabled' => true, 'services.hbx.api_key' => 'key', 'services.hbx.api_secret' => 'secret']);
    $this->seed(SupplierFoundationSeeder::class);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();

    SupplierOperationLog::query()->create([
        'supplier_id' => $supplier->id,
        'correlation_id' => (string) Str::uuid(),
        'operation' => SupplierOperation::CheckRate,
        'request_method' => 'POST',
        'request_url' => '/hotel-api/1.0/checkrates',
        'successful' => true,
        'created_at' => now(),
    ]);

    app(HbxApiCapabilityRegistry::class)->sync();

    expect(HbxApiCapability::query()->where('capability_code', 'booking_check_rate')->value('credential_access_confirmed'))->toBeTrue()
        ->and(HbxApiCapability::query()->where('capability_code', 'payment_data_support')->value('credential_access_confirmed'))->toBeFalse()
        ->and(HbxApiCapability::query()->where('capability_code', 'cache_full')->value('credential_access_confirmed'))->toBeFalse()
        ->and(HbxApiCapability::query()->where('capability_code', 'production_access')->value('credential_access_confirmed'))->toBeFalse();
});
