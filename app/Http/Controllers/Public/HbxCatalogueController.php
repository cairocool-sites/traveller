<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HbxCatalogueController extends Controller
{
    public function destination(string $destination, Request $request): View
    {
        $this->setLocale($request);

        $record = $this->publicDestinations()
            ->where('slug', $destination)
            ->firstOrFail();

        $hotels = HbxHotel::query()
            ->with(['images', 'facilities'])
            ->where('supplier_code', HbxContentSyncService::SUPPLIER_CODE)
            ->where('destination_code', $record->destination_code)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->orderBy('display_order')
            ->orderBy('hotel_name')
            ->limit(24)
            ->get();

        return view('public.catalogue.destination', [
            'destination' => $record,
            'hotels' => $hotels,
            'structuredData' => $this->destinationStructuredData($record),
            'metaTitle' => $record->seo_title ?: __('public.catalogue.destination_meta_title', ['destination' => $this->destinationName($record)]),
            'metaDescription' => $record->seo_description ?: __('public.catalogue.destination_meta_description', ['destination' => $this->destinationName($record)]),
        ]);
    }

    public function hotel(string $destination, string $hotel, Request $request): View
    {
        $this->setLocale($request);

        $destinationRecord = $this->publicDestinations()
            ->where('slug', $destination)
            ->firstOrFail();

        $hotelRecord = HbxHotel::query()
            ->with(['translations', 'images', 'facilities', 'rooms'])
            ->where('supplier_code', HbxContentSyncService::SUPPLIER_CODE)
            ->where('destination_code', $destinationRecord->destination_code)
            ->where('slug', $hotel)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->firstOrFail();

        return view('public.catalogue.hotel', [
            'destination' => $destinationRecord,
            'hotel' => $hotelRecord,
            'structuredData' => $this->hotelStructuredData($destinationRecord, $hotelRecord),
            'metaTitle' => $hotelRecord->seo_title ?: __('public.catalogue.hotel_meta_title', ['hotel' => $this->hotelName($hotelRecord)]),
            'metaDescription' => $hotelRecord->seo_description ?: __('public.catalogue.hotel_meta_description', ['hotel' => $this->hotelName($hotelRecord)]),
        ]);
    }

    public function sitemap(): Response
    {
        $urls = collect([route('home'), route('hotels.index')])
            ->merge($this->publicDestinations()->limit(500)->get()->map(
                fn (HbxDestination $destination): string => route('catalogue.destinations.show', $destination->slug)
            ))
            ->merge(HbxHotel::query()
                ->select(['hbx_hotels.slug', 'hbx_destinations.slug as destination_slug'])
                ->join('hbx_destinations', function ($join): void {
                    $join->on('hbx_hotels.destination_code', '=', 'hbx_destinations.destination_code')
                        ->whereColumn('hbx_hotels.supplier_code', 'hbx_destinations.supplier_code');
                })
                ->where('hbx_hotels.supplier_code', HbxContentSyncService::SUPPLIER_CODE)
                ->where('hbx_hotels.supplier_active', true)
                ->where('hbx_hotels.public_enabled', true)
                ->where('hbx_destinations.supplier_active', true)
                ->where('hbx_destinations.public_enabled', true)
                ->whereNotNull('hbx_destinations.slug')
                ->whereNotNull('hbx_hotels.slug')
                ->limit(1000)
                ->get()
                ->map(fn (HbxHotel $hotel): string => route('catalogue.hotels.show', [
                    'destination' => $hotel->destination_slug,
                    'hotel' => $hotel->slug,
                ])));

        return response()
            ->view('public.catalogue.sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml');
    }

    private function publicDestinations()
    {
        return HbxDestination::query()
            ->where('supplier_code', HbxContentSyncService::SUPPLIER_CODE)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->whereNotNull('slug')
            ->orderBy('display_order')
            ->orderBy('destination_name');
    }

    private function destinationStructuredData(HbxDestination $destination): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('public.nav.home'), 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $this->destinationName($destination), 'item' => route('catalogue.destinations.show', $destination->slug)],
            ],
        ];
    }

    private function hotelStructuredData(HbxDestination $destination, HbxHotel $hotel): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Hotel',
            'name' => $this->hotelName($hotel),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $hotel->address,
                'addressLocality' => $this->destinationName($destination),
                'postalCode' => $hotel->postal_code,
                'addressCountry' => $hotel->country_code,
            ]),
            'url' => route('catalogue.hotels.show', ['destination' => $destination->slug, 'hotel' => $hotel->slug]),
        ];
    }

    private function destinationName(HbxDestination $destination): string
    {
        return app()->getLocale() === 'ar'
            ? ($destination->name_ar ?: $destination->destination_name)
            : ($destination->name_en ?: $destination->destination_name);
    }

    private function hotelName(HbxHotel $hotel): string
    {
        return app()->getLocale() === 'ar'
            ? ($hotel->name_ar ?: $hotel->hotel_name)
            : ($hotel->name_en ?: $hotel->hotel_name);
    }

    private function setLocale(Request $request): void
    {
        $locale = $request->query('locale', session('public_locale', config('app.locale')));

        if (in_array($locale, config('travel.locales.supported'), true)) {
            app()->setLocale($locale);
            session(['public_locale' => $locale]);
        }
    }
}
