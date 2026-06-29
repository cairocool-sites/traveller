<?php

namespace App\Services\Supplier\Exceptions;

use RuntimeException;

class SupplierException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $correlationId = null)
    {
        parent::__construct($message);
    }
}
