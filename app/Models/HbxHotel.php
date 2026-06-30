<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_code', 'hotel_code', 'destination_code', 'country_code', 'zone_code', 'hotel_name', 'category_code', 'star_rating', 'latitude', 'longitude', 'address', 'postal_code', 'accommodation_type_code', 'chain_code', 'primary_phone', 'primary_email', 'supplier_active', 'public_enabled', 'name_ar', 'name_en', 'slug', 'seo_title', 'seo_description', 'display_order', 'last_supplier_update_at', 'last_synced_at', 'payload_checksum', 'is_active', 'synced_at'])]
class HbxHotel extends Model
{
    public function translations(): HasMany
    {
        return $this->hasMany(HbxHotelTranslation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HbxHotelImage::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(HbxHotelFacility::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HbxHotelRoom::class);
    }

    protected function casts(): array
    {
        return [
            'star_rating' => 'integer',
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
