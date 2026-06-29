<?php

namespace App\Models;

use App\Enums\SupplierOperation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'operation', 'idempotency_key', 'request_hash', 'response_snapshot', 'status', 'expires_at'])]
class SupplierIdempotencyRecord extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return [
            'operation' => SupplierOperation::class,
            'response_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
