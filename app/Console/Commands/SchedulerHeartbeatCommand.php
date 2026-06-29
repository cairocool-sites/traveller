<?php

namespace App\Console\Commands;

use App\Models\OperationalHeartbeat;
use Illuminate\Console\Command;

class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';

    protected $description = 'Record the scheduler heartbeat used by readiness checks.';

    public function handle(): int
    {
        OperationalHeartbeat::query()->updateOrCreate(
            ['key' => 'scheduler'],
            ['last_seen_at' => now(), 'metadata' => ['source' => 'schedule']],
        );

        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
