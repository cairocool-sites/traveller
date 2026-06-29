<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['local_entity_type', 'local_entity_id', 'supplier_code', 'supplier_destination_code', 'status', 'confidence', 'manually_confirmed', 'is_active'])]
class SupplierDestinationMapping extends Model
{
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'manually_confirmed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
