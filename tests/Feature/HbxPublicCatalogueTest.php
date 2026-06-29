<?php

use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Models\HbxHotelFacility;
use App\Models\HbxHotelImage;
use App\Models\HbxHotelRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders public destination and hotel catalogue pages from local hbx content without supplier calls', function (): void {
    Http::preventStrayRequests();

    $destination = HbxDestination::query()->create([
        'supplier_code' => 'hbx_hotels',
        'destination_code' => 'CAI',
        'country_code' => 'EG',
        'destination_name' => 'Cairo',
        'content_language' => 'ENG',
        'supplier_active' => true,
        'public_enabled' => true,
        'name_en' => 'Cairo',
        'name_ar' => 'Cairo AR',
        'slug' => 'cairo',
    ]);

    $hotel = HbxHotel::query()->create([
        'supplier_code' => 'hbx_hotels',
        'hotel_code' => '1001',
        'destination_code' => $destination->destination_code,
        'country_code' => 'EG',
        'hotel_name' => 'Cairo Nile Hotel',
        'category_code' => '4EST',
        'address' => 'Nile Street',
        'supplier_active' => true,
        'public_enabled' => true,
        'name_en' => 'Cairo Nile Hotel',
        'slug' => 'cairo-nile-hotel',
    ]);

    HbxHotelImage::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'image_type_code' => 'GEN',
        'path' => 'https://photos.hotelbeds.com/giata/original/00/001001/001001a_hb_a_001.jpg',
        'sort_order' => 1,
        'is_primary' => true,
        'is_active' => true,
    ]);

    HbxHotelFacility::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'facility_code' => '10',
        'description' => 'Wi-Fi',
        'is_active' => true,
    ]);

    HbxHotelRoom::query()->create([
        'hbx_hotel_id' => $hotel->id,
        'room_code' => 'DBL.ST',
        'room_name' => 'Standard room',
        'is_active' => true,
    ]);

    get(route('catalogue.destinations.show', $destination->slug))
        ->assertOk()
        ->assertSee('Cairo')
        ->assertSee('Cairo Nile Hotel')
        ->assertDontSee('rateKey')
        ->assertDontSee('hbx-rate');

    get(route('catalogue.hotels.show', ['destination' => $destination->slug, 'hotel' => $hotel->slug]))
        ->assertOk()
        ->assertSee('Cairo Nile Hotel')
        ->assertSee('Standard room')
        ->assertSee('Wi-Fi')
        ->assertDontSee('rateKey')
        ->assertDontSee('hbx-rate');
});

it('does not render public catalogue records that are disabled by supplier or admin visibility', function (): void {
    $destination = HbxDestination::query()->create([
        'supplier_code' => 'hbx_hotels',
        'destination_code' => 'HRG',
        'country_code' => 'EG',
        'destination_name' => 'Hurghada',
        'content_language' => 'ENG',
        'supplier_active' => true,
        'public_enabled' => false,
        'slug' => 'hurghada',
    ]);

    $hotel = HbxHotel::query()->create([
        'supplier_code' => 'hbx_hotels',
        'hotel_code' => '2001',
        'destination_code' => $destination->destination_code,
        'country_code' => 'EG',
        'hotel_name' => 'Hidden Hotel',
        'supplier_active' => true,
        'public_enabled' => true,
        'slug' => 'hidden-hotel',
    ]);

    get(route('catalogue.destinations.show', $destination->slug))->assertNotFound();
    get(route('catalogue.hotels.show', ['destination' => $destination->slug, 'hotel' => $hotel->slug]))->assertNotFound();
});

it('generates a local sitemap from enabled hbx catalogue records only', function (): void {
    $destination = HbxDestination::query()->create([
        'supplier_code' => 'hbx_hotels',
        'destination_code' => 'ALY',
        'country_code' => 'EG',
        'destination_name' => 'Alexandria',
        'content_language' => 'ENG',
        'supplier_active' => true,
        'public_enabled' => true,
        'slug' => 'alexandria',
    ]);

    HbxHotel::query()->create([
        'supplier_code' => 'hbx_hotels',
        'hotel_code' => '3001',
        'destination_code' => $destination->destination_code,
        'country_code' => 'EG',
        'hotel_name' => 'Alex Sea Hotel',
        'supplier_active' => true,
        'public_enabled' => true,
        'slug' => 'alex-sea-hotel',
    ]);

    get(route('catalogue.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('/destinations/alexandria', false)
        ->assertSee('/hotels/alexandria/alex-sea-hotel', false);
});
