<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'supplier_code',
    'resource_type',
    'resource_code',
    'language',
    'name',
    'country_code',
    'destination_code',
    'parent_code',
    'payload',
    'payload_hash',
    'last_update_time',
    'is_active',
    'synced_at',
])]
class HbxContentResource extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_update_time' => 'datetime',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
