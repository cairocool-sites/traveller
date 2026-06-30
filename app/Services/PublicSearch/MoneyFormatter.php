<?php

namespace App\Services\PublicSearch;

use App\Models\Currency;
use App\Services\Currency\CurrencyConversionService;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;
use Throwable;

class MoneyFormatter
{
    public function __construct(private readonly CurrencyConversionService $conversion) {}

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

    public function formatMinor(int $minorAmount, string $currencyCode): string
    {
        return $this->formatParts($minorAmount, $currencyCode);
    }

    public function approximateEgpFromArray(?array $money): ?string
    {
        if (! $money) {
            return null;
        }

        return $this->approximateEgpFromMinor((int) $money['minor_amount'], (string) $money['currency']);
    }

    public function approximateEgpFromMinor(int $minorAmount, string $currencyCode): ?string
    {
        $currencyCode = strtoupper($currencyCode);

        if ($currencyCode === 'EGP') {
            return null;
        }

        try {
            $amount = $this->decimalFromMinor($minorAmount, $currencyCode);
            $converted = $this->conversion->convert($amount, $currencyCode, 'EGP');
            $egp = Money::fromDecimalString($converted, 'EGP');

            return __('public.price.approx_egp', [
                'amount' => $this->format($egp),
            ]);
        } catch (Throwable) {
            return null;
        }
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

    private function decimalFromMinor(int $minorAmount, string $currencyCode): string
    {
        $currency = Currency::query()
            ->where('code', $currencyCode)
            ->where('is_active', true)
            ->firstOrFail();

        $factor = 10 ** $currency->decimal_places;
        $major = intdiv($minorAmount, $factor);
        $minor = abs($minorAmount % $factor);

        return $major.'.'.str_pad((string) $minor, $currency->decimal_places, '0', STR_PAD_LEFT);
    }
}
