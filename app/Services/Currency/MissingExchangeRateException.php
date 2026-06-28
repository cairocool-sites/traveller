<?php

namespace App\Services\Currency;

use RuntimeException;

class MissingExchangeRateException extends RuntimeException
{
    public static function forPair(string $baseCurrency, string $quoteCurrency): self
    {
        return new self("No active exchange rate exists for {$baseCurrency}/{$quoteCurrency}.");
    }
}
