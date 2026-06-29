<?php

namespace App\Services\Supplier\Transport;

use App\Enums\SupplierOperation;
use App\Models\Supplier;

interface SupplierHttpTransport
{
    public function request(Supplier $supplier, SupplierOperation $operation, string $method, string $path, array $payload = [], array $headers = []): array;
}
