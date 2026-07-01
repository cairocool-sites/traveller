<?php

use App\Enums\SupplierStatus;
use App\Jobs\HbxContentSyncJob;
use App\Models\City;
use App\Models\HbxContentResource;
use App\Models\HbxContentSyncBatch;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\HbxHotelFacility;
use App\Models\HbxHotelImage;
use App\Models\HbxHotelRoom;
use App\Models\HbxHotelTranslation;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\SupplierDestinationMapping;
use App\Models\SupplierOperationLog;
use App\Services\Supplier\Hbx\HbxContentApiClient;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
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
        && $request->hasHeader('X-Signature')
        && $request->hasHeader('Accept-Encoding', 'gzip'));

    $log = SupplierOperationLog::query()->where('request_url', HbxContentApiClient::COUNTRIES_PATH)->firstOrFail();
    $encoded = json_encode([$log->request_headers, $log->request_payload], JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('content-api-key')
        ->and($encoded)->not->toContain('content-api-secret')
        ->and($encoded)->toContain('[REDACTED]');
});

it('uses supplier credential records before hbx env credentials for content api signatures', function () {
    config([
        'services.hbx.api_key' => null,
        'services.hbx.api_secret' => null,
    ]);

    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['countries' => ['countries' => []]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    SupplierCredential::query()->create([
        'supplier_id' => $supplier->id,
        'credential_key' => 'api_key',
        'encrypted_value' => 'supplier-api-key',
        'is_secret' => true,
    ]);
    SupplierCredential::query()->create([
        'supplier_id' => $supplier->id,
        'credential_key' => 'api_secret',
        'encrypted_value' => 'supplier-api-secret',
        'is_secret' => true,
    ]);

    app(HbxContentApiClient::class)->countries($supplier, ['from' => 1, 'to' => 1]);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Api-key', 'supplier-api-key')
        && $request->hasHeader('X-Signature'));

    $log = SupplierOperationLog::query()->where('request_url', HbxContentApiClient::COUNTRIES_PATH)->firstOrFail();
    $encoded = json_encode([$log->request_headers, $log->request_payload], JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain('supplier-api-key')
        ->and($encoded)->not->toContain('supplier-api-secret')
        ->and($encoded)->toContain('[REDACTED]');
});

it('diagnoses the official hotels endpoint without exposing credentials or raw signatures', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response([
        'auditData' => ['timestamp' => '2026-06-30 12:00:00.000'],
        'hotels' => ['hotels' => []],
        'from' => 1,
        'to' => 10,
        'total' => 0,
    ], 200, ['Content-Type' => 'application/json'])]);

    $this->artisan('hbx:content:diagnose-hotels --from=1 --to=10 --language=ENG')
        ->expectsOutputToContain('Resolved base URL: https://api.test.hotelbeds.com')
        ->expectsOutputToContain('Endpoint path: /hotel-content-api/1.0/hotels')
        ->expectsOutputToContain('Query parameters: {"fields":"all","language":"ENG","from":1,"to":10}')
        ->expectsOutputToContain('Api-key header present: yes')
        ->expectsOutputToContain('X-Signature header present: yes')
        ->expectsOutputToContain('Accept-Encoding header: gzip')
        ->expectsOutputToContain('HTTP status: 200')
        ->expectsOutputToContain('Response envelope keys: auditData, hotels, from, to, total')
        ->expectsOutputToContain('Classification: success')
        ->assertSuccessful();

    $output = Artisan::output();

    expect($output)->not->toContain('content-api-key')
        ->and($output)->not->toContain('content-api-secret')
        ->and($output)->not->toContain('X-Signature:');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels')
        && str_contains($request->url(), 'fields=all')
        && str_contains($request->url(), 'language=ENG')
        && str_contains($request->url(), 'from=1')
        && str_contains($request->url(), 'to=10')
        && $request->hasHeader('Accept-Encoding', 'gzip'));
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
        ->and(HbxDestination::query()->where('destination_code', 'GIZ')->value('is_active'))->toBeTrue();
});

it('does not deactivate records from a bounded page unless explicitly requested', function () {
    HbxDestination::query()->create([
        'supplier_code' => 'hbx_hotels',
        'destination_code' => 'OLD',
        'destination_name' => 'Older Destination',
        'country_code' => 'EG',
        'is_active' => true,
        'synced_at' => now(),
    ]);

    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['destinations' => ['destinations' => [
        ['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG'],
    ]]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    app(HbxContentSyncService::class)->syncDestinations($supplier, ['country_code' => 'EG', 'limit' => 1]);

    expect(HbxDestination::query()->where('destination_code', 'OLD')->value('is_active'))->toBeTrue();

    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['destinations' => ['destinations' => [
        ['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG'],
    ]]], 200)]);

    app(HbxContentSyncService::class)->syncDestinations($supplier, ['country_code' => 'EG', 'limit' => 1, 'deactivate_missing' => true]);

    expect(HbxDestination::query()->where('destination_code', 'OLD')->value('is_active'))->toBeFalse();
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
        'images' => [
            ['path' => 'https://photos.hotelbeds.com/giata/original/00/001001/001001a_hb_a_002.jpg', 'type' => ['code' => 'DEP', 'description' => ['content' => 'Sports and Entertainment']], 'visualOrder' => 1, 'order' => 1],
            ['path' => '00/001001/001001a_hb_a_001.jpg', 'imageTypeCode' => 'GEN', 'visualOrder' => 0, 'order' => 1],
        ],
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
        ->and(HbxHotelImage::query()->where('path', '00/001001/001001a_hb_a_001.jpg')->where('is_primary', true)->exists())->toBeTrue()
        ->and(HbxHotelImage::query()->where('path', '00/001001/001001a_hb_a_002.jpg')->exists())->toBeTrue()
        ->and(HbxHotelImage::query()->where('path', '00/001001/001001a_hb_a_002.jpg')->value('image_type_code'))->toBe('DEP')
        ->and(HbxHotelImage::query()->where('path', '00/001001/001001a_hb_a_001.jpg')->firstOrFail()->url())->toBe('https://photos.hotelbeds.com/giata/bigger/00/001001/001001a_hb_a_001.jpg')
        ->and(HbxHotelFacility::query()->where('facility_code', '10')->exists())->toBeTrue()
        ->and(HbxHotelRoom::query()->where('room_code', 'STD')->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels')
        && $request->method() === 'GET');
});

it('syncs hotels by official hotel codes returned from availability', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['hotels' => ['hotels' => [[
        'code' => 2002,
        'name' => ['content' => 'HBX Code Filter Hotel'],
        'destinationCode' => 'CAI',
        'countryCode' => 'EG',
        'categoryCode' => '4EST',
    ]]]], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $result = app(HbxContentSyncService::class)->syncHotels($supplier, '', [
        'hotel_codes' => '2002,not-a-code',
        'from' => 1,
        'to' => 1,
    ]);

    expect($result['processed'])->toBe(1)
        ->and(HbxHotel::query()->where('hotel_code', '2002')->value('hotel_name'))->toBe('HBX Code Filter Hotel');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels')
        && str_contains($request->url(), 'codes=2002'));
});

it('syncs hotel details by official hotel code fallback', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response([
        'hotel' => [
            'code' => 3003,
            'name' => ['content' => 'HBX Details Hotel'],
            'destination' => ['code' => 'CAI'],
            'country' => ['code' => 'EG'],
            'category' => ['code' => '5EST'],
            'address' => ['content' => 'Sandbox Address'],
        ],
    ], 200)]);

    $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
    $result = app(HbxContentSyncService::class)->syncHotelDetailsByCodes($supplier, '3003', ['language' => 'ENG']);

    expect($result['processed'])->toBe(1)
        ->and(HbxHotel::query()->where('hotel_code', '3003')->value('hotel_name'))->toBe('HBX Details Hotel')
        ->and(HbxHotel::query()->where('hotel_code', '3003')->value('destination_code'))->toBe('CAI');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels/3003/details')
        && str_contains($request->url(), 'language=ENG')
        && $request->hasHeader('Accept-Encoding', 'gzip'));
});

it('syncs local hotel details in bounded chunks for images and descriptions', function () {
    foreach (['4001', '4002'] as $code) {
        HbxHotel::query()->create([
            'supplier_code' => 'hbx_hotels',
            'hotel_code' => $code,
            'hotel_name' => 'Local HBX '.$code,
            'destination_code' => 'CAI',
            'country_code' => 'EG',
            'supplier_active' => true,
            'public_enabled' => true,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    Http::fake(function ($request) {
        preg_match('#/hotels/(\d+)/details#', $request->url(), $matches);
        $code = $matches[1] ?? '4001';

        return Http::response(['hotel' => [
            'code' => (int) $code,
            'name' => ['content' => 'Detailed Hotel '.$code],
            'description' => ['content' => 'Description for hotel '.$code],
            'destinationCode' => 'CAI',
            'countryCode' => 'EG',
            'images' => [['path' => '00/'.$code.'/'.$code.'_hb_a_001.jpg', 'visualOrder' => 0, 'order' => 1]],
        ]], 200);
    });

    $this->artisan('hbx:content:sync --resource=hotels --details --local-hotels --country=EG --chunk-size=1 --language=ENG')
        ->expectsOutputToContain('hotels: processed 2; stored 2.')
        ->assertSuccessful();

    expect(HbxHotelTranslation::query()->where('description', 'Description for hotel 4001')->exists())->toBeTrue()
        ->and(HbxHotelImage::query()->where('path', '00/4001/4001_hb_a_001.jpg')->where('is_primary', true)->exists())->toBeTrue()
        ->and(HbxHotelImage::query()->where('path', '00/4002/4002_hb_a_001.jpg')->where('is_primary', true)->exists())->toBeTrue();

    Http::assertSentCount(2);
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
        && data_get($request->data(), 'lastUpdateTime') === '2026-06-01'
        && data_get($request->data(), 'countryCode') === null
        && data_get($request->data(), 'countryCodes') === null
        && data_get($request->data(), 'destinationCode') === null);
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

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/types/boards')
        && str_contains($request->url(), 'language=ENG')
        && ! str_contains($request->url(), 'countryCode=')
        && ! str_contains($request->url(), 'countryCodes=')
        && ! str_contains($request->url(), 'destinationCode='));

    $this->artisan('hbx:content:sync --resource=all --full-authorized-portfolio')
        ->expectsOutputToContain('Full authorized portfolio sync requires --confirm.')
        ->assertFailed();
});

it('maps legacy content resource aliases to official OpenAPI paths and blocks standalone zones', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['groupCategories' => ['groupCategories' => [['code' => 'GRUPO1', 'description' => ['content' => 'Hotel category group']]]]], 200)]);

    $this->artisan('hbx:content:sync --resource=category_groups --page-limit=1')
        ->expectsOutputToContain('groupcategories: processed 1; stored 1.')
        ->assertSuccessful();

    expect(HbxContentResource::query()
        ->where('resource_type', 'groupcategories')
        ->where('resource_code', 'GRUPO1')
        ->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.test.hotelbeds.com/hotel-content-api/1.0/types/groupcategories'));

    $this->artisan('hbx:content:sync --resource=zones --page-limit=1')
        ->expectsOutputToContain('HBX Content API has no standalone zones endpoint')
        ->assertFailed();
});

it('supports official from to limit syntax and local content status commands', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['destinations' => ['destinations' => [['code' => 'CAI', 'name' => ['content' => 'Cairo'], 'countryCode' => 'EG']]]], 200)]);

    $this->artisan('hbx:content:sync --resource=destinations --country=EG --from=1 --to=1')
        ->expectsOutputToContain('destinations: processed 1; stored 1.')
        ->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'from=1')
        && str_contains($request->url(), 'to=1'));

    $this->artisan('hbx:content:status')
        ->expectsOutputToContain('HBX Content API local catalogue status')
        ->expectsOutputToContain('No supplier request was sent by this command.')
        ->assertSuccessful();

    $this->artisan('hbx:content:enable-public --country=EG --dry-run')
        ->expectsOutputToContain('Dry run complete. No public visibility was changed.')
        ->assertSuccessful();
});

it('classifies invalid content api endpoint or schema responses safely', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['error' => ['code' => 'INVALID_DATA']], 400)]);

    $this->artisan('hbx:content:sync --resource=destinations --country=EG --limit=1')
        ->assertFailed();

    expect(HbxContentSyncBatch::query()->latest()->value('status'))->toBe('failed');
});

it('records hbx content sync batch checkpoints without storing secrets', function () {
    Http::fake(['api.test.hotelbeds.com/*' => Http::response(['boards' => ['boards' => [['code' => 'RO', 'description' => ['content' => 'Room Only']]]]], 200)]);

    $this->artisan('hbx:content:sync --resource=boards --country=EG --page-limit=1')
        ->expectsOutputToContain('boards: processed 1; stored 1.')
        ->assertSuccessful();

    $batch = HbxContentSyncBatch::query()->latest()->firstOrFail();
    $encoded = json_encode($batch->checkpoint, JSON_THROW_ON_ERROR);

    expect($batch->status)->toBe('completed')
        ->and($batch->resource)->toBe('boards')
        ->and($batch->mode)->toBe('bounded')
        ->and($batch->processed_count)->toBe(1)
        ->and($batch->stored_count)->toBe(1)
        ->and($encoded)->toContain('boards')
        ->and($encoded)->not->toContain('content-api-key')
        ->and($encoded)->not->toContain('content-api-secret');
});

it('queues hbx content sync batches without making immediate content api requests', function () {
    Bus::fake();
    Http::preventStrayRequests();

    $this->artisan('hbx:content:sync --resource=destinations --country=EG --page-limit=1 --queue')
        ->expectsOutputToContain('Queued HBX content sync batch #')
        ->assertSuccessful();

    $batch = HbxContentSyncBatch::query()->latest()->firstOrFail();

    expect($batch->status)->toBe('pending')
        ->and($batch->resource)->toBe('destinations')
        ->and($batch->queued)->toBeTrue();

    Bus::assertDispatched(HbxContentSyncJob::class);
});

it('keeps production booking endpoint blocked during content work', function () {
    Supplier::query()->where('code', 'hbx_hotels')->update(['base_url' => 'https://api.hotelbeds.com']);

    $this->artisan('hbx:sync-content --destinations --dry-run')
        ->expectsOutputToContain('configured endpoint is not https://api.test.hotelbeds.com')
        ->assertFailed();
});
