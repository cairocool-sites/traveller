<?php

namespace App\Services\PublicSearch;

use App\Models\Currency;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

class MoneyFormatter
{
    public function format(Money $money): string
    {
        return $this->formatParts($money->minorAmount, $money->currency);
    }

    public function formatArray(?array $money): string
    {
        if (! $money) {
            return __('public.price.unavailable');
        }

        return $this->formatParts((int) $money['minor_amount'], $money['currency']);
    }

    private function formatParts(int $minorAmount, string $currencyCode): string
    {
        $currency = Currency::query()
            ->where('code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw ValidationException::withMessages([
                'currency' => __('public.search.validation.currency'),
            ]);
        }

        $factor = 10 ** $currency->decimal_places;
        $major = intdiv($minorAmount, $factor);
        $minor = $minorAmount % $factor;

        return number_format($major).'.'.str_pad((string) $minor, $currency->decimal_places, '0', STR_PAD_LEFT).' '.$currency->code;
    }
}
