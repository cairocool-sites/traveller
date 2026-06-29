<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name_ar', 'name_en', 'instructions_ar', 'instructions_en', 'account_name', 'account_reference', 'supports_attachment', 'requires_reference', 'is_active', 'sort_order'])]
class ManualPaymentMethod extends Model
{
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function localizedName(?string $locale = null): string
    {
        return $this->{'name_'.($locale ?? app()->getLocale())} ?? $this->name_en;
    }

    public function localizedInstructions(?string $locale = null): string
    {
        return $this->{'instructions_'.($locale ?? app()->getLocale())} ?? $this->instructions_en;
    }

    protected function casts(): array
    {
        return [
            'supports_attachment' => 'boolean',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
