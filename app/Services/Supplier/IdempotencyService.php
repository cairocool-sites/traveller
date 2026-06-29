<?php

namespace App\Services\Supplier;

use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Models\SupplierIdempotencyRecord;
use App\Services\Supplier\Exceptions\DuplicateSupplierRequestException;

class IdempotencyService
{
    public function findOrReserve(Supplier $supplier, SupplierOperation $operation, string $key, array $requestPayload): ?array
    {
        $hash = $this->hash($requestPayload);
        $record = SupplierIdempotencyRecord::query()
            ->where('supplier_id', $supplier->id)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();

        if (! $record) {
            SupplierIdempotencyRecord::query()->create([
                'supplier_id' => $supplier->id,
                'operation' => $operation,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'status' => 'reserved',
                'expires_at' => now()->addDay(),
            ]);

            return null;
        }

        if ($record->request_hash !== $hash) {
            throw new DuplicateSupplierRequestException('Idempotency key was reused with a different request payload.');
        }

        return $record->response_snapshot;
    }

    public function complete(Supplier $supplier, SupplierOperation $operation, string $key, array $responseSnapshot): void
    {
        SupplierIdempotencyRecord::query()
            ->where('supplier_id', $supplier->id)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->update([
                'response_snapshot' => $responseSnapshot,
                'status' => 'completed',
            ]);
    }

    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
