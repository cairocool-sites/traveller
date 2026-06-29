<?php

namespace App\Models;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierHealthStatus;
use App\Enums\SupplierIntegrationType;
use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'integration_type', 'environment', 'status', 'priority', 'timeout_seconds', 'connect_timeout_seconds', 'max_retries', 'retry_delay_milliseconds', 'search_enabled', 'details_enabled', 'check_rate_enabled', 'booking_enabled', 'cancellation_enabled', 'booking_lookup_enabled', 'health_check_enabled', 'base_url', 'health_status', 'last_health_check_at', 'last_success_at', 'last_failure_at', 'created_by', 'updated_by'])]
class Supplier extends Model
{
    use HasFactory;

    public function credentials(): HasMany
    {
        return $this->hasMany(SupplierCredential::class);
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(SupplierOperationLog::class);
    }

    public function idempotencyRecords(): HasMany
    {
        return $this->hasMany(SupplierIdempotencyRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'integration_type' => SupplierIntegrationType::class,
            'environment' => SupplierEnvironment::class,
            'status' => SupplierStatus::class,
            'health_status' => SupplierHealthStatus::class,
            'search_enabled' => 'boolean',
            'details_enabled' => 'boolean',
            'check_rate_enabled' => 'boolean',
            'booking_enabled' => 'boolean',
            'cancellation_enabled' => 'boolean',
            'booking_lookup_enabled' => 'boolean',
            'health_check_enabled' => 'boolean',
            'last_health_check_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }
}
