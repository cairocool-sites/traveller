<?php

namespace App\Services\Facility;

use App\Models\Facility;

class FacilityNameResolver
{
    public function resolve(Facility $facility, ?string $locale = null): string
    {
        return $facility->translation($locale)?->name ?? $facility->code;
    }
}
