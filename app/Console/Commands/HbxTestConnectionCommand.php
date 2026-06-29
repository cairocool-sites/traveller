<?php

namespace App\Console\Commands;

use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class HbxTestConnectionCommand extends Command
{
    protected $signature = 'hbx:test-connection';

    protected $description = 'Safely test HBX sandbox connectivity without printing credentials or signatures.';

    public function handle(SupplierManager $suppliers): int
    {
        if (! config('services.hbx.enabled')) {
            $this->warn('HBX sandbox is disabled. Set HBX_ENABLED=true to opt in.');

            return self::SUCCESS;
        }

        if (blank(config('services.hbx.api_key')) || blank(config('services.hbx.api_secret'))) {
            $this->error('HBX sandbox credentials are not configured.');

            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', 'hbx_hotels')->first();

        if (! $supplier) {
            $this->error('HBX sandbox supplier is not seeded. Run php artisan db:seed --class=SupplierFoundationSeeder.');

            return self::FAILURE;
        }

        try {
            $result = $suppliers->resolve('hbx_hotels', SupplierOperation::HealthCheck)->healthCheck();
        } catch (SupplierException $exception) {
            $this->error('HBX sandbox health check failed: '.class_basename($exception));

            return self::FAILURE;
        }

        $this->info($result->healthy ? 'HBX sandbox health check succeeded.' : 'HBX sandbox health check did not report healthy.');
        $this->line('Correlation ID: '.$result->correlationId);

        return $result->healthy ? self::SUCCESS : self::FAILURE;
    }
}
