<?php

namespace App\Services\PublicSearch;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\HbxDestination;
use App\Models\HbxHotel;
use App\Services\PublicSearch\Data\DestinationOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DestinationLookupService
{
    public function search(string $term, string $locale = 'ar', int $limit = 8): Collection
    {
        if (! Schema::hasTable('cities') && ! Schema::hasTable('hbx_destinations')) {
            return collect();
        }

        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Cache::remember("hbx-autocomplete:{$locale}:".mb_strtolower($term).":{$limit}", now()->addMinutes(10), fn (): Collection => collect()
            ->merge($this->hbxDestinations($term, $locale, $limit))
            ->merge($this->hbxHotels($term, $locale, $limit))
            ->merge($this->cities($term, $locale, $limit))
            ->merge($this->areas($term, $locale, $limit))
            ->merge($this->countries($term, $locale, $limit))
            ->take($limit)
            ->values());
    }

    public function resolve(string $token, string $locale = 'ar'): DestinationOption
    {
        [$type, $id] = array_pad(explode(':', $token, 2), 2, null);

        $option = match ($type) {
            'hbx_destination' => $this->hbxDestination((int) $id, $locale),
            'hbx_hotel' => $this->hbxHotel((int) $id, $locale),
            'city' => $this->city((int) $id, $locale),
            'area' => $this->area((int) $id, $locale),
            'country' => $this->country((int) $id, $locale),
            default => null,
        };

        if (! $option) {
            throw ValidationException::withMessages([
                'destination' => __('public.search.validation.destination'),
            ]);
        }

        return $option;
    }

    public function featured(string $locale = 'ar', int $limit = 6): Collection
    {
        if (Schema::hasTable('hbx_destinations') && HbxDestination::query()->where('public_enabled', true)->where('supplier_active', true)->exists()) {
            return HbxDestination::query()
                ->where('supplier_active', true)
                ->where('public_enabled', true)
                ->where('country_code', config('travel.hbx.public_country', 'EG'))
                ->orderBy('display_order')
                ->orderBy('destination_name')
                ->limit($limit)
                ->get()
                ->map(fn (HbxDestination $destination): DestinationOption => $this->hbxDestinationOption($destination, $locale));
        }

        if (! Schema::hasTable('cities')) {
            return collect();
        }

        return City::query()
            ->with('country')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (City $city): DestinationOption => $this->cityOption($city, $locale));
    }

    private function cities(string $term, string $locale, int $limit): Collection
    {
        return City::query()
            ->with('country')
            ->where('is_active', true)
            ->whereHas('country', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->where('name_en', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (City $city): DestinationOption => $this->cityOption($city, $locale));
    }

    private function areas(string $term, string $locale, int $limit): Collection
    {
        return Area::query()
            ->with('city.country')
            ->where('is_active', true)
            ->whereHas('city', fn ($query) => $query->where('is_active', true)->whereHas('country', fn ($country) => $country->where('is_active', true)))
            ->where(fn ($query) => $query->where('name_en', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (Area $area): DestinationOption => $this->areaOption($area, $locale));
    }

    private function countries(string $term, string $locale, int $limit): Collection
    {
        return Country::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('name_en', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->limit($limit)
            ->get()
            ->map(fn (Country $country): DestinationOption => $this->countryOption($country, $locale));
    }

    private function hbxDestinations(string $term, string $locale, int $limit): Collection
    {
        if (! Schema::hasTable('hbx_destinations')) {
            return collect();
        }

        $normalized = $this->normalizeArabic($term);
        $variants = $this->searchVariants($term);

        return HbxDestination::query()
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->where(function ($query) use ($variants, $normalized): void {
                foreach ($variants as $variant) {
                    $query->orWhere('destination_name', 'like', "%{$variant}%")
                        ->orWhere('name_en', 'like', "%{$variant}%")
                        ->orWhere('name_ar', 'like', "%{$variant}%");
                }

                $query->orWhere('slug', 'like', "%{$normalized}%");
            })
            ->orderBy('display_order')
            ->orderBy('destination_name')
            ->limit($limit)
            ->get()
            ->map(fn (HbxDestination $destination): DestinationOption => $this->hbxDestinationOption($destination, $locale));
    }

    private function hbxHotels(string $term, string $locale, int $limit): Collection
    {
        if (! Schema::hasTable('hbx_hotels')) {
            return collect();
        }

        $normalized = $this->normalizeArabic($term);
        $variants = $this->searchVariants($term);

        return HbxHotel::query()
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->where(function ($query) use ($variants, $normalized): void {
                foreach ($variants as $variant) {
                    $query->orWhere('hotel_name', 'like', "%{$variant}%")
                        ->orWhere('name_en', 'like', "%{$variant}%")
                        ->orWhere('name_ar', 'like', "%{$variant}%");
                }

                $query->orWhere('slug', 'like', "%{$normalized}%");
            })
            ->orderBy('display_order')
            ->orderBy('hotel_name')
            ->limit($limit)
            ->get()
            ->map(fn (HbxHotel $hotel): DestinationOption => $this->hbxHotelOption($hotel, $locale));
    }

    private function hbxDestination(int $id, string $locale): ?DestinationOption
    {
        $destination = HbxDestination::query()
            ->whereKey($id)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->first();

        return $destination ? $this->hbxDestinationOption($destination, $locale) : null;
    }

    private function hbxHotel(int $id, string $locale): ?DestinationOption
    {
        $hotel = HbxHotel::query()
            ->whereKey($id)
            ->where('supplier_active', true)
            ->where('public_enabled', true)
            ->first();

        return $hotel ? $this->hbxHotelOption($hotel, $locale) : null;
    }

    private function city(int $id, string $locale): ?DestinationOption
    {
        $city = City::query()->with('country')->whereKey($id)->where('is_active', true)->whereHas('country', fn ($query) => $query->where('is_active', true))->first();

        return $city ? $this->cityOption($city, $locale) : null;
    }

    private function area(int $id, string $locale): ?DestinationOption
    {
        $area = Area::query()->with('city.country')->whereKey($id)->where('is_active', true)->whereHas('city', fn ($query) => $query->where('is_active', true)->whereHas('country', fn ($country) => $country->where('is_active', true)))->first();

        return $area ? $this->areaOption($area, $locale) : null;
    }

    private function country(int $id, string $locale): ?DestinationOption
    {
        $country = Country::query()->whereKey($id)->where('is_active', true)->first();

        return $country ? $this->countryOption($country, $locale) : null;
    }

    private function cityOption(City $city, string $locale): DestinationOption
    {
        $cityName = $locale === 'ar' ? $city->name_ar : $city->name_en;
        $countryName = $locale === 'ar' ? $city->country?->name_ar : $city->country?->name_en;

        return new DestinationOption("city:{$city->id}", 'city', $city->id, "{$cityName}, {$countryName}", $city->name_en);
    }

    private function areaOption(Area $area, string $locale): DestinationOption
    {
        $areaName = $locale === 'ar' ? $area->name_ar : $area->name_en;
        $cityName = $locale === 'ar' ? $area->city?->name_ar : $area->city?->name_en;

        return new DestinationOption("area:{$area->id}", 'area', $area->id, "{$areaName}, {$cityName}", $area->city?->name_en ?? $area->name_en);
    }

    private function countryOption(Country $country, string $locale): DestinationOption
    {
        $name = $locale === 'ar' ? $country->name_ar : $country->name_en;

        return new DestinationOption("country:{$country->id}", 'country', $country->id, $name, $country->name_en);
    }

    private function hbxDestinationOption(HbxDestination $destination, string $locale): DestinationOption
    {
        $name = $locale === 'ar'
            ? ($destination->name_ar ?: $destination->destination_name)
            : ($destination->name_en ?: $destination->destination_name);

        return new DestinationOption("hbx_destination:{$destination->id}", 'hbx_destination', $destination->id, $name, $destination->destination_code);
    }

    private function hbxHotelOption(HbxHotel $hotel, string $locale): DestinationOption
    {
        $name = $locale === 'ar'
            ? ($hotel->name_ar ?: $hotel->hotel_name)
            : ($hotel->name_en ?: $hotel->hotel_name);

        return new DestinationOption("hbx_hotel:{$hotel->id}", 'hbx_hotel', $hotel->id, $name, $hotel->hotel_code);
    }

    private function normalizeArabic(string $value): string
    {
        return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], mb_strtolower($value));
    }

    private function searchVariants(string $term): array
    {
        $normalized = $this->normalizeArabic($term);

        return array_values(array_unique(array_filter([
            $term,
            $normalized,
            preg_replace('/ه$/u', 'ة', $normalized),
            str_replace('ا', 'أ', $normalized),
            str_replace('ا', 'إ', $normalized),
        ])));
    }
}
