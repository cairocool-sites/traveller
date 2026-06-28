<?php

namespace App\Services\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;

class LatestExchangeRateResolver
{
    public function resolve(Currency|string $baseCurrency, Currency|string $quoteCurrency, ?CarbonInterface $at = null): ExchangeRate
    {
        $base = $this->currency($baseCurrency);
        $quote = $this->currency($quoteCurrency);
        $at ??= now();

        if ($base->is($quote)) {
            throw MissingExchangeRateException::forPair($base->code, $quote->code);
        }

        $rate = ExchangeRate::query()
            ->where('base_currency_id', $base->id)
            ->where('quote_currency_id', $quote->id)
            ->where('is_active', true)
            ->where('effective_at', '<=', $at)
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', $at))
            ->orderByDesc('effective_at')
            ->first();

        if (! $rate) {
            throw MissingExchangeRateException::forPair($base->code, $quote->code);
        }

        return $rate;
    }

    private function currency(Currency|string $currency): Currency
    {
        if ($currency instanceof Currency) {
            return $currency;
        }

        return Currency::query()->where('code', strtoupper($currency))->firstOrFail();
    }
}
