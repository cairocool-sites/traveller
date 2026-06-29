<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['public_uuid', 'destination_type', 'destination_id', 'destination_label', 'check_in', 'check_out', 'occupancy', 'nationality', 'residency_country', 'currency', 'locale', 'anonymous_session_id', 'user_id', 'correlation_id', 'criteria_snapshot', 'results_snapshot', 'warnings', 'expires_at'])]
class SearchSession extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'occupancy' => 'array',
            'criteria_snapshot' => 'array',
            'results_snapshot' => 'array',
            'warnings' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
