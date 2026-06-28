<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['base_currency_id', 'quote_currency_id', 'rate', 'source', 'effective_at', 'expires_at', 'is_active', 'created_by'])]
class ExchangeRate extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $exchangeRate): void {
            if ((int) $exchangeRate->base_currency_id === (int) $exchangeRate->quote_currency_id) {
                throw ValidationException::withMessages([
                    'quote_currency_id' => __('validation.custom.exchange_rate.distinct_pair'),
                ]);
            }

            if (bccomp((string) $exchangeRate->rate, '0', 10) <= 0) {
                throw ValidationException::withMessages([
                    'rate' => __('validation.custom.exchange_rate.positive_rate'),
                ]);
            }
        });
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
