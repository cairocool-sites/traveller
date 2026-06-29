<?php

namespace App\Services\Supplier;

use App\Enums\SupplierErrorType;
use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;

class SupplierOperationLogger
{
    public function __construct(private readonly PayloadSanitizer $sanitizer) {}

    public function log(Supplier $supplier, SupplierOperation $operation, array $context): SupplierOperationLog
    {
        return SupplierOperationLog::query()->create([
            'supplier_id' => $supplier->id,
            'correlation_id' => $context['correlation_id'],
            'operation' => $operation,
            'request_method' => $context['request_method'] ?? null,
            'request_url' => $context['request_url'] ?? null,
            'request_headers' => $this->sanitizer->sanitize($context['request_headers'] ?? null),
            'request_payload' => $this->sanitizer->sanitize($context['request_payload'] ?? null),
            'response_status' => $context['response_status'] ?? null,
            'response_headers' => $this->sanitizer->sanitize($context['response_headers'] ?? null),
            'response_payload' => $this->sanitizer->sanitize($context['response_payload'] ?? null),
            'duration_ms' => $context['duration_ms'] ?? null,
            'attempt_number' => $context['attempt_number'] ?? 1,
            'successful' => $context['successful'] ?? false,
            'error_type' => $context['error_type'] ?? null,
            'error_message' => $context['error_message'] ?? null,
            'booking_reference' => $context['booking_reference'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function errorType(SupplierErrorType $type): SupplierErrorType
    {
        return $type;
    }
}
