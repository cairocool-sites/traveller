<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['iso2', 'iso3', 'numeric_code', 'phone_code', 'name_en', 'name_ar', 'nationality_en', 'nationality_ar', 'currency_code', 'is_active', 'sort_order'])]
class Country extends Model
{
    use HasFactory;

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function setIso2Attribute(string $value): void
    {
        $this->attributes['iso2'] = strtoupper($value);
    }

    public function setIso3Attribute(string $value): void
    {
        $this->attributes['iso3'] = strtoupper($value);
    }

    public function setCurrencyCodeAttribute(?string $value): void
    {
        $this->attributes['currency_code'] = $value === null ? null : strtoupper($value);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
