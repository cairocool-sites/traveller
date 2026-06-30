<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_code', 'destination_code', 'zone_code', 'zone_name', 'content_language', 'payload', 'is_active', 'synced_at'])]
class HbxDestinationZone extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
