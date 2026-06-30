<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_id', 'resource', 'mode', 'status', 'country_code', 'destination_code', 'language', 'page_limit', 'last_update_time', 'checkpoint', 'processed_count', 'stored_count', 'error_message', 'dry_run', 'full_authorized_portfolio', 'queued', 'started_at', 'finished_at'])]
class HbxContentSyncBatch extends Model
{
    protected function casts(): array
    {
        return [
            'checkpoint' => 'array',
            'processed_count' => 'integer',
            'stored_count' => 'integer',
            'page_limit' => 'integer',
            'dry_run' => 'boolean',
            'full_authorized_portfolio' => 'boolean',
            'queued' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
