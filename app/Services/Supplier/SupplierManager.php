<?php

namespace App\Services\Supplier;

use App\Enums\SupplierIntegrationType;
use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\Supplier\Contracts\HotelSupplierInterface;
use App\Services\Supplier\Exceptions\DisabledSupplierException;
use App\Services\Supplier\Exceptions\MissingSupplierException;
use App\Services\Supplier\Exceptions\UnsupportedSupplierOperationException;
use App\Services\Supplier\Mock\MockHotelSupplier;
use Illuminate\Support\Collection;

class SupplierManager
{
    public function resolve(string $supplierCode, ?SupplierOperation $operation = null): HotelSupplierInterface
    {
        $supplier = Supplier::query()->where('code', $supplierCode)->first();

        if (! $supplier) {
            throw new MissingSupplierException("Supplier [{$supplierCode}] was not found.");
        }

        $this->assertUsable($supplier, $operation);

        return match ($supplier->integration_type) {
            SupplierIntegrationType::Mock => app(MockHotelSupplier::class, ['supplier' => $supplier]),
            default => throw new UnsupportedSupplierOperationException('No adapter is registered for this supplier integration type.'),
        };
    }

    public function enabledFor(SupplierOperation $operation): Collection
    {
        return Supplier::query()
            ->whereIn('status', [SupplierStatus::Active->value, SupplierStatus::Degraded->value])
            ->where($operation->capabilityColumn(), true)
            ->orderBy('priority')
            ->get();
    }

    public function assertUsable(Supplier $supplier, ?SupplierOperation $operation = null): void
    {
        if (in_array($supplier->status, [SupplierStatus::Inactive, SupplierStatus::Disabled], true)) {
            throw new DisabledSupplierException("Supplier [{$supplier->code}] is not active.");
        }

        if ($operation && ! $supplier->{$operation->capabilityColumn()}) {
            throw new UnsupportedSupplierOperationException("Supplier [{$supplier->code}] does not support [{$operation->value}].");
        }
    }
}
