<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Models\Facility;
use Filament\Resources\Pages\CreateRecord;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    private string $nameEn = '';

    private string $nameAr = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->nameEn = $data['name_en'];
        $this->nameAr = $data['name_ar'];
        unset($data['name_en'], $data['name_ar']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->record instanceof Facility) {
            return;
        }

        $this->record->translations()->createMany([
            ['locale' => 'en', 'name' => $this->nameEn],
            ['locale' => 'ar', 'name' => $this->nameAr],
        ]);
    }
}
