<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hbx_hotel_id', 'image_type_code', 'path', 'room_code', 'sort_order', 'width', 'height', 'alt_text', 'is_primary', 'is_active', 'payload'])]
class HbxHotelImage extends Model
{
    public const CDN_BASE_URL = 'https://photos.hotelbeds.com/giata';

    public const DEFAULT_SIZE = 'bigger';

    public const ALLOWED_SIZES = [
        'original',
        'bigger',
        'large',
        'medium',
        'small',
        'thumb',
    ];

    public function url(string $size = self::DEFAULT_SIZE): string
    {
        $path = $this->normalizedPath();

        if ($path === '') {
            return '';
        }

        if ($this->isExternalUrl($path)) {
            return $path;
        }

        $size = in_array($size, self::ALLOWED_SIZES, true) ? $size : self::DEFAULT_SIZE;

        return self::CDN_BASE_URL.'/'.$size.'/'.$path;
    }

    public function thumbnailUrl(): string
    {
        return $this->url('small');
    }

    public function normalizedPath(): string
    {
        return self::normalizePath((string) $this->path);
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $path = preg_replace('#^https?://photos\.hotelbeds\.com/giata/#i', '', $path) ?? $path;
        $path = ltrim($path, '/');

        foreach (self::ALLOWED_SIZES as $size) {
            $prefix = $size.'/';

            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    private function isExternalUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }
}
