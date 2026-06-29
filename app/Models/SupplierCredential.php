<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'credential_key', 'encrypted_value', 'is_secret'])]
class SupplierCredential extends Model
{
    protected $hidden = ['encrypted_value'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected function casts(): array
    {
        return [
            'encrypted_value' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }
}
