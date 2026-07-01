<?php

namespace App\Console\Commands;

use App\Enums\SupplierOperation;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\RateHawk\RateHawkConfiguration;
use App\Services\Supplier\SupplierManager;
use Illuminate\Console\Command;

class RateHawkTestConnectionCommand extends Command
{
    protected $signature = 'ratehawk:test-connection {--diagnostic : Show safe configuration diagnostics without credentials or live booking calls}';

    protected $description = 'Safely inspect RateHawk supplier configuration without printing credentials or sending bookings.';

    public function handle(SupplierManager $suppliers, RateHawkConfiguration $config): int
    {
        $supplier = Supplier::query()->where('code', 'ratehawk_hotels')->first();

        if (! $supplier) {
            $this->error('RateHawk supplier is not seeded. Run php artisan db:seed --class=SupplierFoundationSeeder.');

            return self::FAILURE;
        }

        $credentials = $config->credentials($supplier);

        $this->line('Supplier: ratehawk_hotels');
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
            $this->line('No external request was sent.');

            return self::SUCCESS;
        }

        if ($supplier->status !== SupplierStatus::Active) {
            $this->warn('RateHawk supplier is present but inactive. No external request was sent.');

            return self::SUCCESS;
        }

        try {
            $adapter = $suppliers->resolve('ratehawk_hotels', SupplierOperation::HealthCheck);
            $result = $adapter->healthCheck();
        } catch (SupplierException $exception) {
            $this->error('RateHawk supplier cannot be resolved: '.class_basename($exception));

            return self::FAILURE;
        }

        $this->line('Health state: '.$result->status->value);
        $this->line('Message: '.$result->message);
        $this->line('Correlation ID: '.$result->correlationId);
        $this->line('No booking, cancellation, or payment request was sent.');

        return self::SUCCESS;
    }
}
