<?php

namespace App\Services\Supplier\Hbx;

use Carbon\CarbonImmutable;

class HbxSignatureService
{
    public function signature(string $apiKey, string $apiSecret, ?CarbonImmutable $timestamp = null): string
    {
        $epoch = ($timestamp ?? CarbonImmutable::now('UTC'))->getTimestamp();

        return hash('sha256', $apiKey.$apiSecret.$epoch);
    }
}
