<?php

namespace App\Models;

use App\Enums\HotelStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['country_id', 'city_id', 'area_id', 'default_currency_id', 'name', 'slug', 'internal_code', 'star_rating', 'property_type', 'status', 'latitude', 'longitude', 'address_line_1', 'address_line_2', 'postal_code', 'primary_phone', 'primary_email', 'website_url', 'check_in_time', 'check_out_time', 'timezone', 'total_rooms', 'year_opened', 'year_renovated', 'is_featured', 'is_active', 'published_at', 'created_by', 'updated_by'])]
class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HotelTranslation::class);
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'hotel_facility')->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(HotelContact::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(HotelPolicy::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function translation(?string $locale = null): ?HotelTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
    }

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'status' => HotelStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
