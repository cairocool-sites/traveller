<?php

namespace App\Services\PublicSearch;

use App\Models\HbxHotel;
use App\Models\SupplierDestinationMapping;
use App\Services\PublicSearch\Data\DestinationOption;
use App\Services\Supplier\Hbx\HbxContentSyncService;
use Illuminate\Validation\ValidationException;

class SupplierDestinationResolver
{
    public function forHbx(DestinationOption $destination): array
    {
        $mapping = SupplierDestinationMapping::query()
            ->where('local_entity_type', $destination->type)
            ->where('local_entity_id', $destination->id)
            ->where('supplier_code', HbxContentSyncService::SUPPLIER_CODE)
            ->where('status', 'confirmed')
            ->where('manually_confirmed', true)
            ->where('is_active', true)
            ->first();

        if (! $mapping) {
            throw ValidationException::withMessages([
                'destination' => __('public.search.validation.destination_not_available'),
            ]);
        }

        $hotelCodes = HbxHotel::query()
            ->where('supplier_code', HbxContentSyncService::SUPPLIER_CODE)
            ->where('destination_code', $mapping->supplier_destination_code)
            ->where('is_active', true)
            ->orderBy('hotel_code')
            ->limit(200)
            ->pluck('hotel_code')
            ->all();

        if ($hotelCodes === []) {
            throw ValidationException::withMessages([
                'destination' => __('public.search.validation.no_synchronized_hotels'),
            ]);
        }

        return [
            'destination_code' => $mapping->supplier_destination_code,
            'hotel_codes' => $hotelCodes,
        ];
    }
}
