<?php

namespace App\Models;

use App\Enums\FacilityCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'icon', 'category', 'is_active', 'sort_order'])]
class Facility extends Model
{
    use HasFactory;

    public function translations(): HasMany
    {
        return $this->hasMany(FacilityTranslation::class);
    }

    public function translation(?string $locale = null): ?FacilityTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtolower($value);
    }

    protected function casts(): array
    {
        return [
            'category' => FacilityCategory::class,
            'is_active' => 'boolean',
        ];
    }
}
