<?php

namespace App\Console\Commands;

use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\Tbo\TboConfiguration;
use Illuminate\Console\Command;

class TboTestConnectionCommand extends Command
{
    protected $signature = 'tbo:test-connection {--diagnostic : Show safe configuration diagnostics without credentials}';

    protected $description = 'Safely inspect TBO supplier configuration without printing credentials or sending bookings.';

    public function handle(SupplierManager $suppliers, TboConfiguration $config): int
    {
        $supplier = Supplier::query()->where('code', 'tbo_hotels')->first();

        if (! $supplier) {
            $this->error('TBO supplier is not seeded. Run php artisan db:seed --class=SupplierFoundationSeeder.');

            return self::FAILURE;
        }

        $credentials = $config->credentials($supplier);

        $this->line('Supplier: tbo_hotels');
        $this->line('Status: '.$supplier->status->value);
        $this->line('Environment: '.$supplier->environment->value);
        $this->line('Base URL configured: '.($config->baseUrl($supplier) !== '' ? 'yes' : 'no'));
        $this->line('Credentials configured: '.($credentials->configured() ? 'yes' : 'no'));
        $this->line('Search enabled: '.($supplier->search_enabled ? 'yes' : 'no'));
        $this->line('Booking enabled: '.($supplier->booking_enabled ? 'yes' : 'no'));
        $this->line('Cancellation enabled: '.($supplier->cancellation_enabled ? 'yes' : 'no'));

        if ($this->option('diagnostic')) {
            $this->line('Endpoint keys:');
            foreach ($config->endpoints() as $key => $path) {
                $this->line("- {$key}: {$path}");
            }
        }

        if ($supplier->status !== SupplierStatus::Active) {
            $this->warn('TBO supplier is present but inactive. No external request was sent.');

            return self::SUCCESS;
        }

        try {
            $adapter = $suppliers->resolve('tbo_hotels', SupplierOperation::HealthCheck);
            $result = $adapter->healthCheck();
        } catch (SupplierException $exception) {
            $this->error('TBO supplier cannot be resolved: '.class_basename($exception));

            return self::FAILURE;
        }

        $this->line('Health state: '.$result->status->value);
        $this->line('Message: '.$result->message);
        $this->line('Correlation ID: '.$result->correlationId);

        return self::SUCCESS;
    }
}
