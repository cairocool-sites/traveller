<?php

use App\Enums\HotelStatus;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Hotel\HotelCatalogException;
use App\Services\Hotel\HotelCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
});

function hotelPayload(array $overrides = []): array
{
    $country = Country::query()->where('iso2', 'EG')->firstOrFail();
    $city = City::query()->where('country_id', $country->id)->where('name_en', 'Cairo')->firstOrFail();

    return array_merge([
        'country_id' => $country->id,
        'city_id' => $city->id,
        'name' => 'Fictional Nile Test Hotel',
        'slug' => 'fictional-nile-test-hotel',
        'internal_code' => 'HTL-TEST-001',
        'property_type' => 'hotel',
        'status' => 'draft',
        'is_active' => true,
        'is_featured' => false,
    ], $overrides);
}

function hotelTranslations(): array
{
    return [
        'en' => ['translated_name' => 'Fictional Nile Test Hotel', 'short_description' => 'A fictional hotel for tests.'],
        'ar' => ['translated_name' => 'فندق نايل الاختباري', 'short_description' => 'فندق خيالي للاختبارات.'],
    ];
}

it('creates hotels with bilingual translations and facilities', function () {
    $facility = Facility::query()->where('code', 'wifi')->firstOrFail();
    $actor = User::factory()->create();

    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations(), [$facility->id], $actor);

    expect($hotel->exists)->toBeTrue()
        ->and($hotel->created_by)->toBe($actor->id)
        ->and($hotel->updated_by)->toBe($actor->id)
        ->and($hotel->translations()->count())->toBe(2)
        ->and($hotel->facilities()->whereKey($facility->id)->exists())->toBeTrue();
});

it('updates hotels without silently changing the slug', function () {
    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());

    app(HotelCatalogService::class)->update($hotel, ['name' => 'Updated Fictional Hotel'], [
        'en' => ['translated_name' => 'Updated Fictional Hotel'],
    ]);

    expect($hotel->refresh()->name)->toBe('Updated Fictional Hotel')
        ->and($hotel->slug)->toBe('fictional-nile-test-hotel');
});

it('enforces unique internal code and slug', function () {
    app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());

    expect(fn () => app(HotelCatalogService::class)->create(
        hotelPayload(['name' => 'Duplicate Code Hotel', 'slug' => 'duplicate-code-hotel']),
        hotelTranslations(),
    ))->toThrow(QueryException::class);

    expect(fn () => app(HotelCatalogService::class)->create(
        hotelPayload(['internal_code' => 'HTL-TEST-002']),
        hotelTranslations(),
    ))->toThrow(QueryException::class);
});

it('validates country city and area consistency', function () {
    $egypt = Country::query()->where('iso2', 'EG')->firstOrFail();
    $dubai = City::query()->where('name_en', 'Dubai')->firstOrFail();

    expect(fn () => app(HotelCatalogService::class)->create(
        hotelPayload(['country_id' => $egypt->id, 'city_id' => $dubai->id, 'internal_code' => 'HTL-GEO-001', 'slug' => 'geo-error']),
        hotelTranslations(),
    ))->toThrow(HotelCatalogException::class);

    $cairo = City::query()->where('name_en', 'Cairo')->firstOrFail();
    $alexandria = City::query()->where('name_en', 'Alexandria')->firstOrFail();
    $area = Area::query()->create(['city_id' => $alexandria->id, 'name_en' => 'Test Area', 'name_ar' => 'منطقة اختبار']);

    expect(fn () => app(HotelCatalogService::class)->create(
        hotelPayload(['city_id' => $cairo->id, 'area_id' => $area->id, 'internal_code' => 'HTL-GEO-002', 'slug' => 'area-error']),
        hotelTranslations(),
    ))->toThrow(HotelCatalogException::class);
});

it('syncs facilities and enforces a single primary image', function () {
    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());
    $wifi = Facility::query()->where('code', 'wifi')->firstOrFail();
    $parking = Facility::query()->where('code', 'parking')->firstOrFail();

    app(HotelCatalogService::class)->syncFacilities($hotel, [$wifi->id, $parking->id]);

    expect($hotel->facilities()->count())->toBe(2);

    $first = app(HotelCatalogService::class)->addImage($hotel, [
        'path' => 'hotels/first.webp',
        'mime_type' => 'image/webp',
        'file_size' => 1000,
        'image_type' => 'exterior',
        'is_primary' => true,
    ]);
    $second = app(HotelCatalogService::class)->addImage($hotel, [
        'path' => 'hotels/second.webp',
        'mime_type' => 'image/webp',
        'file_size' => 1000,
        'image_type' => 'lobby',
        'is_primary' => true,
    ]);

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue()
        ->and($hotel->images()->where('is_primary', true)->count())->toBe(1);
});

it('validates image metadata', function () {
    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());

    expect(fn () => app(HotelCatalogService::class)->addImage($hotel, [
        'path' => '../secret.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1000,
        'image_type' => 'other',
    ]))->toThrow(HotelCatalogException::class);

    expect(fn () => app(HotelCatalogService::class)->addImage($hotel, [
        'path' => 'hotels/file.svg',
        'mime_type' => 'image/svg+xml',
        'file_size' => 1000,
        'image_type' => 'other',
    ]))->toThrow(HotelCatalogException::class);
});

it('publishes only authorized and valid hotels', function () {
    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());
    $contentManager = User::factory()->create();
    $contentManager->assignRole('content_manager');
    $operations = User::factory()->create();
    $operations->assignRole('operations_admin');

    $this->actingAs($contentManager);
    expect(Gate::allows('publish', $hotel))->toBeTrue();

    app(HotelCatalogService::class)->publish($hotel, $contentManager);
    expect($hotel->refresh()->status)->toBe(HotelStatus::Published)
        ->and($hotel->published_at)->not->toBeNull();

    $this->actingAs($operations);
    expect(Gate::denies('publish', $hotel))->toBeTrue();

    $invalid = app(HotelCatalogService::class)->create(
        hotelPayload(['internal_code' => 'HTL-NO-TRANS', 'slug' => 'no-trans']),
        [],
    );

    expect(fn () => app(HotelCatalogService::class)->publish($invalid, $contentManager))->toThrow(HotelCatalogException::class);
});

it('keeps auditors read only and blocks unauthorized hotel admin access', function () {
    $hotel = app(HotelCatalogService::class)->create(hotelPayload(), hotelTranslations());

    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');
    $this->actingAs($auditor);

    expect(Gate::allows('view', $hotel))->toBeTrue()
        ->and(Gate::denies('update', $hotel))->toBeTrue()
        ->and(Gate::denies('delete', $hotel))->toBeTrue();

    $unauthorized = User::factory()->create();
    $unauthorized->assignRole('reservation_agent');

    $this->actingAs($unauthorized)
        ->get('/admin/hotels')
        ->assertForbidden();
});

it('rolls back service transactions on invalid translations', function () {
    expect(fn () => app(HotelCatalogService::class)->create(
        hotelPayload(['internal_code' => 'HTL-ROLLBACK', 'slug' => 'rollback-hotel']),
        ['fr' => ['translated_name' => 'Invalid locale']],
    ))->toThrow(HotelCatalogException::class);

    expect(Hotel::query()->where('internal_code', 'HTL-ROLLBACK')->exists())->toBeFalse();
});
