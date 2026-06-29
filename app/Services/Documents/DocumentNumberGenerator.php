<?php

namespace App\Services\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentNumberGenerator
{
    public function make(string $prefix, string $modelClass, string $column): string
    {
        do {
            $number = $prefix.'-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (is_subclass_of($modelClass, Model::class) && $modelClass::query()->where($column, $number)->exists());

        return $number;
    }

    public function token(): string
    {
        return Str::random(64);
    }
}
