<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_id', 'from_status', 'to_status', 'reason', 'metadata', 'changed_by'])]
class RefundStatusHistory extends Model
{
    public $timestamps = false;

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
