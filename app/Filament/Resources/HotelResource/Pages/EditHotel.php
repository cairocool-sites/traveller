<?php

namespace App\Filament\Resources\HotelResource\Pages;

use App\Filament\Resources\HotelResource;
use App\Models\Hotel;
use App\Services\Hotel\HotelCatalogService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditHotel extends EditRecord
{
    protected static string $resource = HotelResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Hotel) {
            $record->loadMissing(['translations', 'facilities']);

            foreach (['en', 'ar'] as $locale) {
                $translation = $record->translations->firstWhere('locale', $locale);
                $data["translation_{$locale}_name"] = $translation?->translated_name;
                $data["translation_{$locale}_short_description"] = $translation?->short_description;
                $data["translation_{$locale}_description"] = $translation?->description;
                $data["translation_{$locale}_address_text"] = $translation?->address_text;
                $data["translation_{$locale}_meta_title"] = $translation?->meta_title;
                $data["translation_{$locale}_meta_description"] = $translation?->meta_description;
            }

            $data['facility_ids'] = $record->facilities->pluck('id')->all();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$hotelData, $translations, $facilityIds] = $this->extractPayload($data);

        return app(HotelCatalogService::class)->update($record, $hotelData, $translations, $facilityIds, Auth::user());
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
