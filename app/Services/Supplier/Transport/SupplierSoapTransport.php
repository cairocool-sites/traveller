<?php

namespace App\Services\Supplier\Transport;

interface SupplierSoapTransport
{
    public function call(string $operation, array $payload, array $options = []): array;
}
