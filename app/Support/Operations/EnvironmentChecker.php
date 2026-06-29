<?php

namespace App\Support\Operations;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnvironmentChecker
{
    /**
     * @return array<int, string>
     */
    public function requiredPhpExtensions(): array
    {
        $extensions = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'intl', 'json', 'mbstring', 'openssl', 'pcre', 'PDO', 'session', 'tokenizer', 'xml', 'zip'];

        if ((bool) config('travel.suppliers.soap_enabled', false)) {
            $extensions[] = 'soap';
        }

        if (config('database.default') === 'mysql') {
            $extensions[] = 'pdo_mysql';
        }

        return array_values(array_unique($extensions));
    }

    public function check(): EnvironmentCheckResult
    {
        $failures = [];
        $warnings = [];
        $passes = [];
        $isProduction = app()->environment('production');

        $this->requireFilled(config('app.key'), 'APP_KEY exists', $passes, $failures);

        if ($isProduction && config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        } else {
            $passes[] = 'Debug mode is acceptable for the current environment.';
        }

        if ($isProduction && ! Str::startsWith((string) config('app.url'), 'https://')) {
            $failures[] = 'APP_URL must use HTTPS in production.';
        } else {
            $passes[] = 'Application URL scheme is acceptable.';
        }

        $this->requireFilled(config('database.default'), 'Database connection is configured', $passes, $failures);
        $this->requireFilled(config('mail.default'), 'Mail transport is configured', $passes, $warnings);

        foreach ($this->requiredPhpExtensions() as $extension) {
            if (! extension_loaded($extension)) {
                $failures[] = "Required PHP extension is missing: {$extension}.";
            }
        }

        if ($isProduction && config('queue.default') === 'sync' && ! (bool) env('TRAVEL_ALLOW_SYNC_QUEUE_IN_PRODUCTION', false)) {
            $failures[] = 'QUEUE_CONNECTION must not be sync in production unless explicitly allowed.';
        }

        if ($isProduction && in_array(config('cache.default'), ['array', 'null'], true)) {
            $warnings[] = 'Production cache should use Redis, database, file, or a configured failover store.';
        }

        if ($isProduction && config('session.secure') !== true) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }

        if (in_array(config('cache.default'), ['redis', 'redis_failover'], true)) {
            $this->requireFilled(config('database.redis.default.host') ?: config('database.redis.default.url'), 'Redis host or URL is configured', $passes, $warnings);
        }

        foreach ([storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs')] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                $failures[] = "Required application directory is not writable: {$this->safePathLabel($directory)}.";
            }
        }

        try {
            Storage::disk('local')->put('ops/.writable-check', 'ok');
            Storage::disk('local')->delete('ops/.writable-check');
            $passes[] = 'Private storage is writable.';
        } catch (\Throwable) {
            $failures[] = 'Private storage is not writable.';
        }

        if (! file_exists(public_path('storage'))) {
            $warnings[] = 'Public storage link is missing; run storage:link if public uploads are enabled.';
        }

        foreach ($this->placeholderKeys() as $key) {
            $value = env($key);
            if (is_string($value) && in_array(strtolower($value), ['changeme', 'change-me', 'password', 'secret', 'example'], true)) {
                $failures[] = "{$key} still appears to use an obvious placeholder value.";
            }
        }

        $passes[] = 'Scheduler must be configured externally with a one-minute cron entry.';

        return new EnvironmentCheckResult($failures, $warnings, $passes);
    }

    /**
     * @param  array<int, string>  $passes
     * @param  array<int, string>  $problems
     */
    private function requireFilled(mixed $value, string $message, array &$passes, array &$problems): void
    {
        if (blank($value)) {
            $problems[] = "{$message}.";

            return;
        }

        $passes[] = "{$message}.";
    }

    private function safePathLabel(string $path): string
    {
        return str_replace(base_path(), '[base_path]', $path);
    }

    /**
     * @return array<int, string>
     */
    private function placeholderKeys(): array
    {
        return ['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'ADMIN_PASSWORD'];
    }
}
