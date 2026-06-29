<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minorAmount,
        public string $currency,
        public int $decimalPlaces = 2,
    ) {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        if (! in_array($currency, config('travel.currency.supported', []), true)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }
    }

    public static function fromDecimalString(string $amount, string $currency, int $decimalPlaces = 2): self
    {
        if (! preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException('Money amount must be a positive decimal string.');
        }

        [$major, $minor] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = str_pad(substr($minor, 0, $decimalPlaces), $decimalPlaces, '0');

        return new self(((int) $major * (10 ** $decimalPlaces)) + (int) $minor, $currency, $decimalPlaces);
    }

    public function decimal(): string
    {
        $factor = 10 ** $this->decimalPlaces;
        $major = intdiv($this->minorAmount, $factor);
        $minor = $this->minorAmount % $factor;

        return $major.'.'.str_pad((string) $minor, $this->decimalPlaces, '0', STR_PAD_LEFT);
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->decimal(),
            'minor_amount' => $this->minorAmount,
            'currency' => $this->currency,
            'decimal_places' => $this->decimalPlaces,
        ];
    }
}
