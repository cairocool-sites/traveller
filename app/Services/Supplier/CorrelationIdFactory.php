<?php

namespace App\Services\Supplier;

use Illuminate\Support\Str;

class CorrelationIdFactory
{
    public function make(?string $correlationId = null): string
    {
        return $correlationId ?: (string) Str::uuid();
    }
}
