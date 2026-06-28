<?php

namespace App\Services\Hotel;

use App\Enums\HotelStatus;
use App\Models\Area;
use App\Models\City;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HotelCatalogService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<int, int>  $facilityIds
     */
    public function create(array $data, array $translations = [], array $facilityIds = [], ?User $actor = null): Hotel
    {
        return DB::transaction(function () use ($data, $translations, $facilityIds, $actor): Hotel {
            $this->assertGeography($data);
            $data = $this->prepareHotelData($data, $actor, creating: true);

            $hotel = Hotel::query()->create($data);
            $this->syncTranslations($hotel, $translations);
            $this->syncFacilities($hotel, $facilityIds);

            return $hotel->refresh()->load(['translations', 'facilities']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<int, int>  $facilityIds
     */
    public function update(Hotel $hotel, array $data, array $translations = [], ?array $facilityIds = null, ?User $actor = null): Hotel
    {
        return DB::transaction(function () use ($hotel, $data, $translations, $facilityIds, $actor): Hotel {
            $merged = array_merge($hotel->only(['country_id', 'city_id', 'area_id']), $data);
            $this->assertGeography($merged);

            $data = $this->prepareHotelData($data, $actor, creating: false);
            $hotel->update($data);
            $this->syncTranslations($hotel, $translations);

            if ($facilityIds !== null) {
                $this->syncFacilities($hotel, $facilityIds);
            }

            return $hotel->refresh()->load(['translations', 'facilities']);
        });
    }

    /**
     * @param  array<int, int>  $facilityIds
     */
    public function syncFacilities(Hotel $hotel, array $facilityIds): void
    {
        $hotel->facilities()->sync(array_values(array_unique($facilityIds)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addImage(Hotel $hotel, array $data, ?User $actor = null): HotelImage
    {
        return DB::transaction(function () use ($hotel, $data, $actor): HotelImage {
            $this->assertImageMetadata($data);

            $data['uploaded_by'] = $actor?->id ?? $data['uploaded_by'] ?? null;
            $data['disk'] = $data['disk'] ?? 'public';
            $data['is_active'] = $data['is_active'] ?? true;
            $data['is_primary'] = $data['is_primary'] ?? false;

            if ($data['is_primary']) {
                $hotel->images()->update(['is_primary' => false]);
            }

            return $hotel->images()->create($data);
        });
    }

    public function setPrimaryImage(Hotel $hotel, HotelImage $image): void
    {
        if (! $image->hotel()->is($hotel)) {
            throw new HotelCatalogException('The image does not belong to the selected hotel.');
        }

        DB::transaction(function () use ($hotel, $image): void {
            $hotel->images()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true, 'is_active' => true])->save();
        });
    }

    public function publish(Hotel $hotel, ?User $actor = null): Hotel
    {
        return DB::transaction(function () use ($hotel, $actor): Hotel {
            $hotel->loadMissing('translations');
            $this->assertPublishable($hotel);

            $hotel->forceFill([
                'status' => HotelStatus::Published,
                'is_active' => true,
                'published_at' => $hotel->published_at ?? now(),
                'updated_by' => $actor?->id ?? $hotel->updated_by,
            ])->save();

            return $hotel->refresh();
        });
    }

    public function unpublish(Hotel $hotel, ?User $actor = null): Hotel
    {
        $hotel->forceFill([
            'status' => HotelStatus::Draft,
            'published_at' => null,
            'updated_by' => $actor?->id ?? $hotel->updated_by,
        ])->save();

        return $hotel->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertGeography(array $data): void
    {
        if (! isset($data['country_id'], $data['city_id'])) {
            return;
        }

        $city = City::query()->findOrFail($data['city_id']);

        if ((int) $city->country_id !== (int) $data['country_id']) {
            throw new HotelCatalogException('The selected city does not belong to the selected country.');
        }

        if (! empty($data['area_id'])) {
            $area = Area::query()->findOrFail($data['area_id']);

            if ((int) $area->city_id !== (int) $data['city_id']) {
                throw new HotelCatalogException('The selected area does not belong to the selected city.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareHotelData(array $data, ?User $actor, bool $creating): array
    {
        if ($creating) {
            $data['status'] = $data['status'] ?? HotelStatus::Draft;
            $data['property_type'] = $data['property_type'] ?? 'hotel';
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
            $data['created_by'] = $actor?->id ?? $data['created_by'] ?? null;
        }

        $data['updated_by'] = $actor?->id ?? $data['updated_by'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function syncTranslations(Hotel $hotel, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            if (! in_array($locale, ['ar', 'en'], true)) {
                throw new HotelCatalogException('Hotel translations support Arabic and English only.');
            }

            if (blank($translation['translated_name'] ?? null)) {
                continue;
            }

            $hotel->translations()->updateOrCreate(
                ['locale' => $locale],
                Arr::only($translation, ['translated_name', 'short_description', 'description', 'address_text', 'meta_title', 'meta_description']),
            );
        }
    }

    private function assertPublishable(Hotel $hotel): void
    {
        if (blank($hotel->country_id) || blank($hotel->city_id) || blank($hotel->name)) {
            throw new HotelCatalogException('A hotel requires country, city, and canonical name before publishing.');
        }

        $this->assertGeography($hotel->only(['country_id', 'city_id', 'area_id']));

        if (! $hotel->translations->contains(fn ($translation): bool => in_array($translation->locale, ['ar', 'en'], true) && filled($translation->translated_name))) {
            throw new HotelCatalogException('A hotel requires at least one Arabic or English translation before publishing.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertImageMetadata(array $data): void
    {
        $path = (string) ($data['path'] ?? '');

        if (blank($path) || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            throw new HotelCatalogException('Invalid image path.');
        }

        $mimeType = (string) ($data['mime_type'] ?? '');
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if ($mimeType !== '' && ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new HotelCatalogException('Invalid image MIME type.');
        }

        if (($data['file_size'] ?? 0) > 5 * 1024 * 1024) {
            throw new HotelCatalogException('Hotel image files must not exceed 5 MB.');
        }
    }
}
