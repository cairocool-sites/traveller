<?php

namespace App\Services\Supplier\Transport;

use App\Services\Supplier\Exceptions\UnavailableSupplierException;

class DeferredSupplierSoapTransport implements SupplierSoapTransport
{
    public function call(string $operation, array $payload, array $options = []): array
    {
        throw new UnavailableSupplierException('SOAP transport is scaffolded for future suppliers but no endpoint is configured in Phase 5.');
    }
}
