<?php

namespace Database\Seeders;

use App\Enums\SupplierEnvironment;
use App\Enums\SupplierHealthStatus;
use App\Enums\SupplierIntegrationType;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierFoundationSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::query()->updateOrCreate(
            ['code' => 'mock_hotels'],
            [
                'name' => 'Mock Hotels Supplier',
                'integration_type' => SupplierIntegrationType::Mock,
                'environment' => SupplierEnvironment::Sandbox,
                'status' => SupplierStatus::Active,
                'priority' => 10,
                'timeout_seconds' => 10,
                'connect_timeout_seconds' => 3,
                'max_retries' => 1,
                'retry_delay_milliseconds' => 100,
                'search_enabled' => true,
                'details_enabled' => true,
                'check_rate_enabled' => true,
                'booking_enabled' => true,
                'cancellation_enabled' => true,
                'booking_lookup_enabled' => true,
                'health_check_enabled' => true,
                'base_url' => null,
                'health_status' => SupplierHealthStatus::Unknown,
            ],
        );
    }
}
