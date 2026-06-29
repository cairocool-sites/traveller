<?php

namespace App\Services\Supplier;

class PayloadSanitizer
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'api_key',
        'apikey',
        'access_token',
        'token',
        'password',
        'secret',
        'credential',
        'card',
        'card_number',
        'cvv',
        'passport',
        'national_id',
        'email',
        'phone',
        'mobile',
    ];

    public function sanitize(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $clean = [];

            foreach ($payload as $key => $value) {
                $stringKey = is_string($key) ? strtolower($key) : (string) $key;
                $clean[$key] = $this->isSensitiveKey($stringKey) ? '[REDACTED]' : $this->sanitize($value);
            }

            return $clean;
        }

        return $payload;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
