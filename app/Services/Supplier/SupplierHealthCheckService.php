<?php

namespace App\Services\Supplier;

use App\Enums\SupplierHealthStatus;
use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\Data\SupplierHealthResultData;
use Illuminate\Support\Facades\Auth;

class SupplierHealthCheckService
{
    public function __construct(private readonly SupplierManager $manager) {}

    public function check(Supplier $supplier): SupplierHealthResultData
    {
        $adapter = $this->manager->resolve($supplier->code, SupplierOperation::HealthCheck);
        $result = $adapter->healthCheck();

        $supplier->forceFill([
            'health_status' => $result->status,
            'last_health_check_at' => $result->checkedAt,
            'last_success_at' => $result->healthy ? now() : $supplier->last_success_at,
            'last_failure_at' => $result->healthy ? $supplier->last_failure_at : now(),
            'updated_by' => Auth::id(),
            'status' => $result->status === SupplierHealthStatus::Unavailable ? $supplier->status : $supplier->status,
        ])->save();

        return $result;
    }
}
