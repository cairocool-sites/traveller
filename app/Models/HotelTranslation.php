<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['hotel_id', 'locale', 'translated_name', 'short_description', 'description', 'address_text', 'meta_title', 'meta_description'])]
class HotelTranslation extends Model
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

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
