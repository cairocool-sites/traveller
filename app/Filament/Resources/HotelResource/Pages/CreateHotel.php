<?php

namespace App\Filament\Resources\HotelResource\Pages;

use App\Filament\Resources\HotelResource;
use App\Services\Hotel\HotelCatalogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateHotel extends CreateRecord
{
    protected static string $resource = HotelResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        [$hotelData, $translations, $facilityIds] = $this->extractPayload($data);

        return app(HotelCatalogService::class)->create($hotelData, $translations, $facilityIds, Auth::user());
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>, 2: array<int, int>}
     */
    private function extractPayload(array $data): array
    {
        $facilityIds = array_values($data['facility_ids'] ?? []);
        unset($data['facility_ids']);

        $translations = [
            'en' => [
                'translated_name' => $data['translation_en_name'] ?? null,
                'short_description' => $data['translation_en_short_description'] ?? null,
                'description' => $data['translation_en_description'] ?? null,
                'address_text' => $data['translation_en_address_text'] ?? null,
                'meta_title' => $data['translation_en_meta_title'] ?? null,
                'meta_description' => $data['translation_en_meta_description'] ?? null,
            ],
            'ar' => [
                'translated_name' => $data['translation_ar_name'] ?? null,
                'short_description' => $data['translation_ar_short_description'] ?? null,
                'description' => $data['translation_ar_description'] ?? null,
                'address_text' => $data['translation_ar_address_text'] ?? null,
                'meta_title' => $data['translation_ar_meta_title'] ?? null,
                'meta_description' => $data['translation_ar_meta_description'] ?? null,
            ],
        ];

        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'translation_')) {
                unset($data[$key]);
            }
        }

        return [$data, $translations, $facilityIds];
    }
}
