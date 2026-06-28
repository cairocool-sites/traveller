<?php

namespace App\Models;

use App\Enums\HotelImageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['hotel_id', 'disk', 'path', 'original_filename', 'mime_type', 'file_size', 'width', 'height', 'image_type', 'alt_text', 'caption', 'sort_order', 'is_primary', 'is_active', 'uploaded_by'])]
class HotelImage extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if ($image->is_primary) {
                static::query()
                    ->where('hotel_id', $image->hotel_id)
                    ->whereKeyNot($image->getKey())
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return [
            'image_type' => HotelImageType::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
