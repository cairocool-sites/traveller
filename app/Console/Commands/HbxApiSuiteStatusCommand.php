<?php

namespace App\Console\Commands;

use App\Services\Supplier\Hbx\HbxApiCapabilityRegistry;
use Illuminate\Console\Command;

class HbxApiSuiteStatusCommand extends Command
{
    protected $signature = 'hbx:api-suite:status {--sync : Refresh capability rows from code definitions}';

    protected $description = 'Display sanitized HBX Hotels API Suite capability status without making supplier calls.';

    public function handle(HbxApiCapabilityRegistry $registry): int
    {
        $capabilities = $registry->sync();

        $this->info('HBX Hotels API Suite capability matrix');
        $this->line('No supplier request was sent by this command.');

        $this->table(
            ['Capability', 'Family', 'Implemented', 'Configured', 'Credential access', 'Sandbox tested', 'Admin', 'Public', 'Production'],
            $capabilities->map(fn ($capability): array => [
                $capability->capability_code,
                $capability->api_family,
                $this->yesNo($capability->implemented),
                $this->yesNo($capability->configured),
                $this->yesNo($capability->credential_access_confirmed),
                $this->yesNo($capability->sandbox_tested),
                $this->yesNo($capability->admin_enabled),
                $this->yesNo($capability->public_enabled),
                $this->yesNo($capability->production_enabled),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
