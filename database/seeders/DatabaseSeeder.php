<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminFoundationSeeder::class);
        $this->call(CoreReferenceDataSeeder::class);
        $this->call(SupplierFoundationSeeder::class);
        $this->call(ManualPaymentMethodSeeder::class);
    }
}
