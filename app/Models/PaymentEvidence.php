<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'file_path', 'original_name', 'mime_type', 'file_size', 'checksum', 'uploaded_at'])]
class PaymentEvidence extends Model
{
    protected $table = 'payment_evidences';

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }
}
