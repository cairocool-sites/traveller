<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'from_status', 'to_status', 'reason', 'metadata', 'changed_by'])]
class PaymentStatusHistory extends Model
{
    public $timestamps = false;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
