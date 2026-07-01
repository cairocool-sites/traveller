<?php

namespace App\Console\Commands;

use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\Hbx\HbxConfiguration;
use App\Services\Supplier\Hbx\HbxHotelSupplier;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class HbxTestConnectionCommand extends Command
{
    protected $signature = 'hbx:test-connection {--diagnostic : Show safe request diagnostics without headers or raw payloads}';

    protected $description = 'Safely test HBX sandbox connectivity without printing credentials or signatures.';

    public function handle(SupplierManager $suppliers, HbxConfiguration $config): int
    {
        if (! config('services.hbx.enabled')) {
            $this->warn('HBX sandbox is disabled. Set HBX_ENABLED=true to opt in.');

            return self::SUCCESS;
        }

        $supplier = Supplier::query()->where('code', 'hbx_hotels')->first();

        if (! $supplier) {
            $this->error('HBX sandbox supplier is not seeded. Run php artisan db:seed --class=SupplierFoundationSeeder.');

            return self::FAILURE;
        }

        if (! $config->hasCredentials($supplier)) {
            $this->error('HBX sandbox credentials are not configured.');

            return self::FAILURE;
        }

        try {
            $adapter = $suppliers->resolve('hbx_hotels', SupplierOperation::HealthCheck);
            $diagnostics = $adapter instanceof HbxHotelSupplier ? $adapter->healthDiagnostics() : null;
            $result = $adapter->healthCheck();
        } catch (SupplierException $exception) {
            if ($this->option('diagnostic') && isset($diagnostics)) {
                $this->line('Target host: '.$diagnostics->targetHost);
                $this->line('Target path: '.$diagnostics->targetPath);
                $this->line('HTTP method: '.$diagnostics->method);
                $this->line('Connect timeout seconds: '.$diagnostics->connectTimeoutSeconds);
                $this->line('Total timeout seconds: '.$diagnostics->timeoutSeconds);
                $this->line('Proxy configured: '.($diagnostics->proxyConfigured ? 'yes' : 'no'));
                $this->line('HTTP response received: no');
            }

            $this->error('HBX sandbox health check failed: '.class_basename($exception));

            return self::FAILURE;
        }

        if ($this->option('diagnostic') && isset($diagnostics)) {
            $diagnostics = $diagnostics->withResponse($adapter instanceof HbxHotelSupplier ? $adapter->lastHealthHttpStatus() : null);
            $this->line('Target host: '.$diagnostics->targetHost);
            $this->line('Target path: '.$diagnostics->targetPath);
            $this->line('HTTP method: '.$diagnostics->method);
            $this->line('Connect timeout seconds: '.$diagnostics->connectTimeoutSeconds);
            $this->line('Total timeout seconds: '.$diagnostics->timeoutSeconds);
            $this->line('Proxy configured: '.($diagnostics->proxyConfigured ? 'yes' : 'no'));
            $this->line('HTTP response received: '.($diagnostics->responseReceived ? 'yes' : 'no'));
        }

        $this->info($result->healthy ? 'HBX sandbox health check succeeded.' : 'HBX sandbox health check did not report healthy.');
        $this->line('Correlation ID: '.$result->correlationId);

        return $result->healthy ? self::SUCCESS : self::FAILURE;
    }
}
