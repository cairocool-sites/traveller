<?php

namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Models\Facility;
use Filament\Resources\Pages\EditRecord;

class EditFacility extends EditRecord
{
    protected static string $resource = FacilityResource::class;

    private string $nameEn = '';

    private string $nameAr = '';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Facility) {
            $record->loadMissing('translations');
            $data['name_en'] = $record->translations->firstWhere('locale', 'en')?->name;
            $data['name_ar'] = $record->translations->firstWhere('locale', 'ar')?->name;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->nameEn = $data['name_en'];
        $this->nameAr = $data['name_ar'];
        unset($data['name_en'], $data['name_ar']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->record instanceof Facility) {
            return;
        }

        foreach (['en' => $this->nameEn, 'ar' => $this->nameAr] as $locale => $name) {
            $this->record->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $name],
            );
        }
    }
}
