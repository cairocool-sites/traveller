<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'operation_type', 'local_reference', 'supplier_reference', 'supplier_status', 'summary_status', 'field_results', 'sanitized_snapshot', 'voucher_evidence', 'cancellation_simulation', 'created_by'])]
class BookingCertificationEvidence extends Model
{
    protected $table = 'booking_certification_evidences';

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'field_results' => 'array',
            'sanitized_snapshot' => 'array',
            'voucher_evidence' => 'array',
            'cancellation_simulation' => 'array',
        ];
    }
}
