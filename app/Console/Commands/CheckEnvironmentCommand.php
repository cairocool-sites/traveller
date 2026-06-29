<?php

namespace App\Console\Commands;

use App\Support\Operations\EnvironmentChecker;
use Illuminate\Console\Command;

class CheckEnvironmentCommand extends Command
{
    protected $signature = 'app:check-environment {--json : Output sanitized JSON}';

    protected $description = 'Run safe production-readiness checks without displaying secrets.';

    public function handle(EnvironmentChecker $checker): int
    {
        $result = $checker->check();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $result->hasFailures(),
                'failures' => $result->failures,
                'warnings' => $result->warnings,
                'passes' => $result->passes,
            ], JSON_THROW_ON_ERROR));
        } else {
            foreach ($result->passes as $pass) {
                $this->info("[OK] {$pass}");
            }

            foreach ($result->warnings as $warning) {
                $this->warn("[WARN] {$warning}");
            }

            foreach ($result->failures as $failure) {
                $this->error("[FAIL] {$failure}");
            }
        }

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
