<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class HbxContentSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $batchId,
        private readonly array $options,
    ) {}

    public function handle(): void
    {
        Artisan::call('hbx:content:sync', array_filter([
            '--resource' => $this->options['resource'] ?? null,
            '--country' => $this->options['country'] ?? null,
            '--destination' => $this->options['destination'] ?? null,
            '--language' => $this->options['language'] ?? 'ENG',
            '--limit' => $this->options['limit'] ?? null,
            '--from' => $this->options['from'] ?? null,
            '--to' => $this->options['to'] ?? null,
            '--page-limit' => $this->options['page_limit'] ?? 1,
            '--last-update-time' => $this->options['last_update_time'] ?? null,
            '--deactivate-missing' => (bool) ($this->options['deactivate_missing'] ?? false),
            '--full-authorized-portfolio' => (bool) ($this->options['full_authorized_portfolio'] ?? false),
            '--confirm' => (bool) ($this->options['confirm'] ?? false),
            '--dry-run' => (bool) ($this->options['dry_run'] ?? false),
            '--batch-id' => $this->batchId,
        ], fn (mixed $value): bool => $value !== null && $value !== false && $value !== ''));
    }
}
