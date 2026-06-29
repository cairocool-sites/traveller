<?php

namespace App\Services\PublicSearch;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Services\PublicSearch\Data\DestinationOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DestinationLookupService
{
    public function search(string $term, string $locale = 'ar', int $limit = 8): Collection
    {
        if (! Schema::hasTable('cities')) {
            return collect();
        }

        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return collect()
            ->merge($this->cities($term, $locale, $limit))
            ->merge($this->areas($term, $locale, $limit))
            ->merge($this->countries($term, $locale, $limit))
            ->take($limit)
            ->values();
    }

    public function resolve(string $token, string $locale = 'ar'): DestinationOption
    {
        [$type, $id] = array_pad(explode(':', $token, 2), 2, null);

        $option = match ($type) {
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
}
