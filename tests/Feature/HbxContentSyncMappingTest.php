<?php

use App\Enums\SupplierStatus;
use App\Models\City;
use App\Models\HbxContentResource;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\HbxHotelFacility;
use App\Models\HbxHotelImage;
use App\Models\HbxHotelRoom;
use App\Models\HbxHotelTranslation;
use App\Models\Supplier;
use App\Models\SupplierDestinationMapping;
use App\Models\SupplierOperationLog;
use App\Services\Supplier\Hbx\HbxContentApiClient;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config([
        'services.hbx.enabled' => true,
        'services.hbx.api_key' => 'content-api-key',
        'services.hbx.api_secret' => 'content-api-secret',
        'services.hbx.base_url' => 'https://api.test.hotelbeds.com',
        'services.hbx.sandbox_booking_enabled' => false,
    ]);

    $this->seed();

    Supplier::query()->where('code', 'hbx_hotels')->update(['status' => SupplierStatus::Active]);
});

it('uses official hbx content api endpoints with signed sanitized requests', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['countries' => ['countries' => []]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    app(HbxContentApiClient::class)->countries($supplier, ['from' => 1, 'to' => 100]);

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/locations/countries')
        && $request->method() === 'GET'
        && $request->hasHeader('Api-key')
        && $request->hasHeader('X-Signature'));

    $log = SupplierOperationLog::query()->where('request_url', HbxContentApiClient::COUNTRIES_PATH)->firstOrFail();
    $encoded = json_encode([$log->request_headers, $log->request_payload], JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('content-api-key')
        ->and($encoded)->not->toContain('content-api-secret')
        ->and($encoded)->toContain('[REDACTED]');
});

it('syncs destinations with pagination and idempotent upserts', function () {
    Http::fakeSequence()
        ->push(['destinations' => ['destinations' => [
            ['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG'],
        ]]], 200)
        ->push(['destinations' => ['destinations' => [
            ['code' => 'GIZ', 'name' => ['content' => 'Giza'], 'countryCode' => 'EG'],
        ]]], 200)
        ->push(['destinations' => ['destinations' => [
            ['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG'],
        ]]], 200);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $result = app(HbxContentSyncService::class)->syncDestinations($supplier, ['country_code' => 'EG', 'page_limit' => 2]);
    $again = app(HbxContentSyncService::class)->syncDestinations($supplier, ['country_code' => 'EG', 'page_limit' => 1]);

    expect($result['processed'])->toBe(2)
        ->and($again['stored'])->toBe(1)
        ->and(HbxDestination::query()->where('destination_code', 'CAI')->count())->toBe(1)
        ->and(HbxDestination::query()->where('destination_code', 'GIZ')->value('is_active'))->toBeFalse();
});

it('syncs hotels for a bounded destination and prevents duplicates', function () {
    HbxDestination::query()->create(['supplier_code' => 'hbx_hotels', 'destination_code' => 'CAI', 'destination_name' => 'Cairo', 'country_code' => 'EG', 'is_active' => true, 'synced_at' => now()]);
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['hotels' => ['hotels' => [[
        'code' => 1001,
        'name' => ['content' => 'HBX Cairo Hotel'],
        'destinationCode' => 'CAI',
        'categoryCode' => '5EST',
        'coordinates' => ['latitude' => '30.0444', 'longitude' => '31.2357'],
        'address' => ['content' => 'Tahrir Square'],
        'postalCode' => '11511',
        'accommodationTypeCode' => 'HOTEL',
        'chainCode' => 'CCT',
        'images' => [['path' => 'https://photos.hotelbeds.com/giata/00/001001/001001a_hb_a_001.jpg', 'imageTypeCode' => 'GEN']],
        'facilities' => [['facilityCode' => 10, 'facilityGroupCode' => 20, 'description' => ['content' => 'Wi-Fi']]],
        'rooms' => [['roomCode' => 'STD', 'description' => ['content' => 'Standard Room'], 'characteristicCode' => 'ST']],
    ]]]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    app(HbxContentSyncService::class)->syncHotels($supplier, 'CAI');
    app(HbxContentSyncService::class)->syncHotels($supplier, 'CAI');

    expect(HbxHotel::query()->where('hotel_code', '1001')->count())->toBe(1)
        ->and(HbxHotel::query()->where('hotel_code', '1001')->value('star_rating'))->toBe(5)
        ->and(HbxHotel::query()->where('hotel_code', '1001')->value('address'))->toBe('Tahrir Square')
        ->and(HbxHotel::query()->where('hotel_code', '1001')->value('postal_code'))->toBe('11511')
        ->and(HbxHotelTranslation::query()->where('language', 'ENG')->exists())->toBeTrue()
        ->and(HbxHotelImage::query()->where('path', 'https://photos.hotelbeds.com/giata/00/001001/001001a_hb_a_001.jpg')->exists())->toBeTrue()
        ->and(HbxHotelFacility::query()->where('facility_code', '10')->exists())->toBeTrue()
        ->and(HbxHotelRoom::query()->where('room_code', 'STD')->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels')
        && $request->method() === 'GET');
});

it('syncs generic hbx content resources with idempotent payload storage', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['boards' => ['boards' => [[
        'code' => 'BB',
        'description' => ['content' => 'Bed and Breakfast'],
        'lastUpdateTime' => '2026-06-01',
    ]]]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $result = app(HbxContentSyncService::class)->syncGenericResource($supplier, 'boards', [
        'country_code' => 'EG',
        'page_limit' => 1,
        'last_update_time' => '2026-06-01',
    ]);

    expect($result['processed'])->toBe(1)
        ->and(HbxContentResource::query()->where('resource_type', 'boards')->where('resource_code', 'BB')->count())->toBe(1)
        ->and(HbxContentResource::query()->where('resource_code', 'BB')->value('name'))->toBe('Bed and Breakfast');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/types/boards')
        && $request->method() === 'GET'
        && data_get($request->data(), 'countryCode') === 'EG'
        && data_get($request->data(), 'lastUpdateTime') === '2026-06-01');
});

it('suggests and allows confirmation of cairo destination mappings', function () {
    HbxDestination::query()->create(['supplier_code' => 'hbx_hotels', 'destination_code' => 'CAI', 'destination_name' => 'Cairo', 'country_code' => 'EG', 'is_active' => true, 'synced_at' => now()]);

    $count = app(HbxContentSyncService::class)->suggestDestinationMappings();
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();
    $mapping = SupplierDestinationMapping::query()->where('local_entity_type', 'city')->where('local_entity_id', $city->id)->firstOrFail();

    $mapping->update(['status' => 'confirmed', 'manually_confirmed' => true]);

    expect($count)->toBeGreaterThan(0)
        ->and($mapping->fresh()->supplier_destination_code)->toBe('CAI')
        ->and($mapping->fresh()->status)->toBe('confirmed')
        ->and($mapping->fresh()->manually_confirmed)->toBeTrue();
});

it('sync command dry-run sends content requests but writes no records', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['destinations' => ['destinations' => [['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG']]]], 200)]);

    $this->artisan('hbx:sync-content --destinations --dry-run --page-limit=1')
        ->expectsOutputToContain('Destinations processed: 1; stored: 0.')
        ->assertSuccessful();

    expect(HbxDestination::query()->where('destination_code', 'CAI')->exists())->toBeFalse();
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/locations/destinations'));
});

it('new content sync command supports official resource names and blocks unconfirmed full portfolio', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['boards' => ['boards' => [['code' => 'RO', 'description' => ['content' => 'Room Only']]]]], 200)]);

    $this->artisan('hbx:content:sync --resource=boards --country=EG --dry-run --page-limit=1')
        ->expectsOutputToContain('boards: processed 1; stored 0.')
        ->expectsOutputToContain('No booking, modification, cancellation, or production request was sent by this command.')
        ->assertSuccessful();

    expect(HbxContentResource::query()->where('resource_code', 'RO')->exists())->toBeFalse();

    $this->artisan('hbx:content:sync --resource=all --full-authorized-portfolio')
        ->expectsOutputToContain('Full authorized portfolio sync requires --confirm.')
        ->assertFailed();
});

it('keeps production booking endpoint blocked during content work', function () {
    Supplier::query()->where('code', 'hbx_hotels')->update(['base_url' => 'https://api.hotelbeds.com']);

    $this->artisan('hbx:sync-content --destinations --dry-run')
        ->expectsOutputToContain('configured endpoint is not https://api.test.hotelbeds.com')
        ->assertFailed();
});
