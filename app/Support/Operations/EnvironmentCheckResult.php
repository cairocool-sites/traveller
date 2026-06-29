<?php

namespace App\Support\Operations;

final readonly class EnvironmentCheckResult
{
    /**
     * @param  array<int, string>  $failures
     * @param  array<int, string>  $warnings
     * @param  array<int, string>  $passes
     */
    public function __construct(
        public array $failures = [],
        public array $warnings = [],
        public array $passes = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
