<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_code', 'destination_code', 'destination_name', 'country_code', 'parent_destination_code', 'is_active', 'synced_at'])]
class HbxDestination extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
