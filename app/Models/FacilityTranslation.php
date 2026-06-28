<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['facility_id', 'locale', 'name', 'description'])]
class FacilityTranslation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $translation): void {
            if (! in_array($translation->locale, ['ar', 'en'], true)) {
                throw ValidationException::withMessages([
                    'locale' => __('validation.custom.locale.supported'),
                ]);
            }
        });
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
