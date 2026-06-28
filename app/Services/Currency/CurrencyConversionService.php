<?php

namespace App\Services\Currency;

use App\Models\Currency;
use Carbon\CarbonInterface;

class CurrencyConversionService
{
    public function __construct(private readonly LatestExchangeRateResolver $rates) {}

    /**
     * Amounts are accepted and returned as decimal strings to avoid binary floating point drift.
     */
    public function convert(string $amount, Currency|string $baseCurrency, Currency|string $quoteCurrency, ?CarbonInterface $at = null): string
    {
        $quote = $quoteCurrency instanceof Currency
            ? $quoteCurrency
            : Currency::query()->where('code', strtoupper($quoteCurrency))->firstOrFail();

        $rate = $this->rates->resolve($baseCurrency, $quote, $at);
        $rawAmount = bcmul($amount, (string) $rate->rate, 10);

        return $this->round($rawAmount, $quote->decimal_places);
    }

    private function round(string $amount, int $decimalPlaces): string
    {
        $factor = bcpow('10', (string) $decimalPlaces, 0);
        $scaled = bcmul($amount, $factor, 10);
        $adjustment = str_starts_with($scaled, '-') ? '-0.5' : '0.5';
        $rounded = bcadd($scaled, $adjustment, 0);

        return bcdiv($rounded, $factor, $decimalPlaces);
    }
}
