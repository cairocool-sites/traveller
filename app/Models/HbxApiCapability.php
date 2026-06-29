<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'supplier_code',
    'capability_code',
    'api_family',
    'display_name',
    'api_version',
    'http_method',
    'endpoint_path',
    'implemented',
    'configured',
    'credential_access_confirmed',
    'sandbox_tested',
    'production_enabled',
    'admin_enabled',
    'public_enabled',
    'last_successful_call_at',
    'last_sanitized_failure',
    'notes',
])]
class HbxApiCapability extends Model
{
    protected function casts(): array
    {
        return [
            'implemented' => 'boolean',
            'configured' => 'boolean',
            'credential_access_confirmed' => 'boolean',
            'sandbox_tested' => 'boolean',
            'production_enabled' => 'boolean',
            'admin_enabled' => 'boolean',
            'public_enabled' => 'boolean',
            'last_successful_call_at' => 'datetime',
        ];
    }
}
