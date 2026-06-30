<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_code', 'destination_code', 'destination_name', 'country_code', 'parent_destination_code', 'content_language', 'destination_type', 'latitude', 'longitude', 'supplier_active', 'public_enabled', 'name_ar', 'name_en', 'slug', 'seo_title', 'seo_description', 'display_order', 'last_supplier_update_at', 'last_synced_at', 'payload_checksum', 'is_active', 'synced_at'])]
class HbxDestination extends Model
{
    public function zones(): HasMany
    {
        return $this->hasMany(HbxDestinationZone::class, 'destination_code', 'destination_code')
            ->where('supplier_code', $this->supplier_code);
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(HbxHotel::class, 'destination_code', 'destination_code')
            ->where('supplier_code', $this->supplier_code);
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'supplier_active' => 'boolean',
            'public_enabled' => 'boolean',
            'display_order' => 'integer',
            'last_supplier_update_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
