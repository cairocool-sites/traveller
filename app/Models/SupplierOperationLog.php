<?php

namespace App\Models;

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'correlation_id', 'operation', 'request_method', 'request_url', 'request_headers', 'request_payload', 'response_status', 'response_headers', 'response_payload', 'duration_ms', 'attempt_number', 'successful', 'error_type', 'error_message', 'booking_reference', 'created_at'])]
class SupplierOperationLog extends Model
{
    public const UPDATED_AT = null;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return [
            'operation' => SupplierOperation::class,
            'request_headers' => 'array',
            'request_payload' => 'array',
            'response_headers' => 'array',
            'response_payload' => 'array',
            'successful' => 'boolean',
            'error_type' => SupplierErrorType::class,
            'created_at' => 'datetime',
        ];
    }
}
