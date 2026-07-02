<?php

use App\Enums\HotelStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\HbxHotel;
use App\Models\HbxHotelFacility;
use App\Models\HbxHotelImage;
use App\Models\HbxHotelTranslation;
use App\Models\Hotel;
use App\Models\SearchSession;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Hotel\HotelCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
});

function publicSearchCriteria(array $overrides = []): array
{
    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();

    return array_merge([
        'destination' => "city:{$city->id}",
        'check_in' => now()->addDays(8)->toDateString(),
        'check_out' => now()->addDays(11)->toDateString(),
        'rooms' => 1,
        'adults' => 2,
        'children' => 0,
        'currency' => config('travel.currency.default'),
        'locale' => 'ar',
    ], $overrides);
}

function createPublishedCanonicalHotel(array $overrides = []): Hotel
{
    $country = Country::query()->where('iso2', 'EG')->firstOrFail();
    $city = City::query()->where('country_id', $country->id)->where('name_en', 'Cairo')->firstOrFail();

    return app(HotelCatalogService::class)->create(array_merge([
        'country_id' => $country->id,
        'city_id' => $city->id,
        'name' => 'Canonical Nile Hotel',
        'slug' => 'canonical-nile-hotel',
        'internal_code' => 'HTL-TEST-001',
        'property_type' => 'hotel',
        'status' => HotelStatus::Published->value,
        'is_active' => true,
        'is_featured' => false,
        'published_at' => now(),
    ], $overrides), [
        'en' => ['translated_name' => 'Canonical Nile Hotel', 'description' => 'Canonical editorial hotel description.'],
        'ar' => ['translated_name' => 'Canonical Nile Hotel AR', 'description' => 'Canonical Arabic editorial hotel description.'],
    ]);
}

it('loads the public homepage with Arabic RTL by default and English LTR when selected', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('Cairo Cool Travel')
        ->assertSee('اعثر على إقامة');

    $this->get('/?locale=en')
        ->assertOk()
        ->assertSee('dir="ltr"', false)
        ->assertSee('Find a stay that feels right');
});

it('rejects invalid dates occupancy child ages inactive destinations and unsupported locale', function () {
    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'check_in' => now()->subDay()->toDateString(),
    ])))->assertRedirect('/hotels?locale=ar')->assertSessionHasErrors('check_in');

    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'check_out' => now()->addDays(7)->toDateString(),
    ])))->assertSessionHasErrors('check_out');

    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'adults' => 0,
    ])))->assertSessionHasErrors('rooms');

    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'children' => 2,
        'child_ages' => [5],
    ])))->assertSessionHasErrors('child_ages');

    $city = City::query()->where('name_en', 'Cairo')->firstOrFail();
    $city->forceFill(['is_active' => false])->save();

    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'destination' => "city:{$city->id}",
    ])))->assertSessionHasErrors('destination');

    $this->from('/hotels')->get(route('hotels.search', publicSearchCriteria([
        'locale' => 'fr',
    ])))->assertSessionHasErrors('locale');
});

it('searches the mock supplier and hides supplier identifiers and net prices', function () {
    $response = $this->get(route('hotels.search', publicSearchCriteria()));

    $response->assertOk()
        ->assertSee('Mock Cairo Nile Hotel')
        ->assertSee('2,500.00 USD')
        ->assertDontSee('MCK-CAI-001')
        ->assertDontSee('2200.00')
        ->assertDontSee('mock_hotels');

    $session = SearchSession::query()->firstOrFail();

    expect($session->public_uuid)->not->toBe((string) $session->id)
        ->and($session->results_snapshot)->not->toBeEmpty();
});

it('handles no availability and supplier failures safely', function (string $scenario, string $expectedText) {
    $this->get(route('hotels.search', publicSearchCriteria(['scenario' => $scenario])))
        ->assertOk()
        ->assertSee($expectedText)
        ->assertDontSee('SupplierAuthenticationException')
        ->assertDontSee('mock_hotels');
})->with([
    ['no_availability', 'لا توجد إقامات مطابقة'],
    ['timeout', 'استغرق البحث وقتا أطول'],
    ['authentication_failure', 'إتاحة الفنادق غير متاحة مؤقتا'],
    ['rate_limited', 'البحث مشغول مؤقتا'],
]);

it('filters and sorts stored normalized search results without exposing sensitive data', function () {
    $this->get(route('hotels.search', publicSearchCriteria()))->assertOk();
    $session = SearchSession::query()->firstOrFail();

    $this->get(route('hotels.search', [
        'session' => $session->public_uuid,
        'locale' => 'ar',
        'refundability' => 'non_refundable',
        'sort' => 'price_desc',
    ]))
        ->assertOk()
        ->assertSee('non refundable')
        ->assertDontSee('MCK-');
});

it('shows canonical hotel content on details and blocks inactive canonical hotels', function () {
    $hotel = createPublishedCanonicalHotel();
    $this->get(route('hotels.search', publicSearchCriteria()))->assertOk();
    $session = SearchSession::query()->firstOrFail();
    $token = $session->results_snapshot[0]['public_token'];

    $this->get(route('hotels.show', ['hotel' => $token, 'search' => $session->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Canonical editorial hotel description')
        ->assertSee('Check rate')
        ->assertDontSee('MCK-CAI-001');

    $hotel->forceFill(['is_active' => false])->save();

    $this->get(route('hotels.show', ['hotel' => $hotel->slug, 'locale' => 'en']))->assertNotFound();
});

it('handles unmapped supplier hotels and expired search sessions safely', function () {
    $this->get(route('hotels.search', publicSearchCriteria()))->assertOk();
    $session = SearchSession::query()->firstOrFail();
    $token = $session->results_snapshot[1]['public_token'];

    $this->get(route('hotels.show', ['hotel' => $token, 'search' => $session->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('safe availability data');

    $session->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->get(route('hotels.show', ['hotel' => $token, 'search' => $session->public_uuid, 'locale' => 'en']))
        ->assertNotFound();
});

it('enriches HBX search hotel detail pages with locally synced content', function () {
    $hotel = HbxHotel::query()->create([
        'supplier_code' => 'hbx_hotels',
        'hotel_code' => '777001',
        'destination_code' => 'HRG',
        'country_code' => 'EG',
        'hotel_name' => 'Stored HBX Hotel',
        'star_rating' => 5,
        'address' => 'Stored HBX Address',
        'supplier_active' => true,
        'public_enabled' => true,
        'is_active' => true,
        'synced_at' => now(),
    ]);

    HbxHotelTranslation::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'language' => 'ENG',
        'name' => 'Stored HBX Hotel',
        'description' => 'Stored HBX description from Content API.',
        'address' => 'Stored HBX translated address',
    ]);

    HbxHotelImage::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'image_type_code' => 'GEN',
        'path' => '00/777/777_hb_a_001.jpg',
        'sort_order' => 1,
        'is_primary' => true,
        'is_active' => true,
    ]);

    HbxHotelFacility::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'facility_code' => 'wifi',
        'description' => 'Stored HBX Wi-Fi',
        'is_active' => true,
    ]);

    $session = SearchSession::query()->create([
        'public_uuid' => (string) Str::uuid(),
        'destination_type' => 'hbx_destination',
        'destination_id' => 1,
        'destination_label' => 'Hurghada',
        'check_in' => now()->addDays(8)->toDateString(),
        'check_out' => now()->addDays(11)->toDateString(),
        'occupancy' => [['adults' => 2, 'children' => 0, 'child_ages' => []]],
        'currency' => 'USD',
        'locale' => 'en',
        'correlation_id' => (string) Str::uuid(),
        'criteria_snapshot' => [],
        'results_snapshot' => [[
            'public_token' => 'opaque-hotel-token',
            'supplier_hotel_id' => '777001',
            'supplier_code' => 'hbx_hotels',
            'canonical_hotel_id' => null,
            'name' => 'Availability Name',
            'star_rating' => 4,
            'location' => 'Availability Location',
            'facilities' => [],
            'rates' => [[
                'public_rate_token' => 'opaque-rate-token',
                'room_name' => 'Double room',
                'board_basis' => 'bed_and_breakfast',
                'total' => ['minor_amount' => 12000, 'amount' => '120.00', 'currency' => 'USD'],
                'refundability' => 'refundable',
                'cancellation_summary' => 'Free cancellation window may apply.',
                'occupancy' => ['adults' => 2, 'children' => 0],
                'requires_check_rate' => true,
            ]],
            'minimum_price_minor' => 12000,
            'currency' => 'USD',
        ]],
        'warnings' => [],
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->get(route('hotels.show', ['hotel' => 'opaque-hotel-token', 'search' => $session->public_uuid, 'locale' => 'en']))
        ->assertOk()
        ->assertSee('Stored HBX Hotel')
        ->assertSee('Stored HBX description from Content API.')
        ->assertSee('Stored HBX Wi-Fi')
        ->assertSee('https://photos.hotelbeds.com/giata/bigger/00/777/777_hb_a_001.jpg')
        ->assertDontSee('safe availability data')
        ->assertDontSee('777001');
});

it('uses USD as the payable search currency and shows optional EGP estimates', function () {
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();
    $egp = Currency::query()->where('code', 'EGP')->firstOrFail();

    ExchangeRate::query()->create([
        'base_currency_id' => $usd->id,
        'quote_currency_id' => $egp->id,
        'rate' => '50.0000000000',
        'source' => 'manual',
        'effective_at' => now(),
        'is_active' => true,
    ]);

    $this->get(route('hotels.search', publicSearchCriteria(['currency' => 'EGP'])))
        ->assertOk()
        ->assertSee('2,500.00 USD')
        ->assertSee('125,000.00 EGP');

    expect(fn () => app(CurrencyConversionService::class)->convert('10.00', 'EGP', Currency::query()->where('code', 'USD')->firstOrFail()))
        ->toThrow(MissingExchangeRateException::class);
});

it('creates non sequential expiring search sessions', function () {
    $this->get(route('hotels.search', publicSearchCriteria()))->assertOk();
    $first = SearchSession::query()->firstOrFail();

    $this->get(route('hotels.search', publicSearchCriteria(['check_in' => now()->addDays(12)->toDateString(), 'check_out' => now()->addDays(14)->toDateString()])))->assertOk();
    $second = SearchSession::query()->latest('id')->firstOrFail();

    expect($first->public_uuid)->not->toBe($second->public_uuid)
        ->and($first->public_uuid)->not->toBe((string) $first->id)
        ->and($first->expires_at->isFuture())->toBeTrue();
});
