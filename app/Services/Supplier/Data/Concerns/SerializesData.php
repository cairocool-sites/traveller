<?php

namespace App\Services\Supplier\Data\Concerns;

trait SerializesData
{
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
