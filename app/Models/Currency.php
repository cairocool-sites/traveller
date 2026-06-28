<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'numeric_code', 'name_en', 'name_ar', 'symbol', 'decimal_places', 'rounding_increment', 'is_active', 'is_base', 'sort_order'])]
class Currency extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $currency): void {
            if ($currency->is_base && ! $currency->is_active) {
                throw ValidationException::withMessages([
                    'is_active' => __('validation.custom.currency.base_must_be_active'),
                ]);
            }

            if ($currency->is_base) {
                static::query()
                    ->whereKeyNot($currency->getKey())
                    ->where('is_base', true)
                    ->update(['is_base' => false]);
            }

            if ($currency->exists && $currency->getOriginal('is_base') && $currency->isDirty('is_active') && ! $currency->is_active) {
                $hasOtherActiveBase = static::query()
                    ->whereKeyNot($currency->getKey())
                    ->where('is_base', true)
                    ->where('is_active', true)
                    ->exists();

                if (! $hasOtherActiveBase) {
                    throw ValidationException::withMessages([
                        'is_active' => __('validation.custom.currency.only_base_currency'),
                    ]);
                }
            }
        });
    }

    public function baseExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_id');
    }

    public function quoteExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'quote_currency_id');
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }

    protected function casts(): array
    {
        return [
            'rounding_increment' => 'decimal:6',
            'is_active' => 'boolean',
            'is_base' => 'boolean',
        ];
    }
}
