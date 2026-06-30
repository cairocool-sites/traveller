<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hbx_api_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 64)->default('hbx_hotels');
            $table->string('capability_code', 100);
            $table->string('api_family', 80);
            $table->string('display_name');
            $table->string('api_version', 20)->default('1.0');
            $table->string('http_method', 12)->nullable();
            $table->string('endpoint_path')->nullable();
            $table->boolean('implemented')->default(false);
            $table->boolean('configured')->default(false);
            $table->boolean('credential_access_confirmed')->default(false);
            $table->boolean('sandbox_tested')->default(false);
            $table->boolean('production_enabled')->default(false);
            $table->boolean('admin_enabled')->default(false);
            $table->boolean('public_enabled')->default(false);
            $table->timestamp('last_successful_call_at')->nullable();
            $table->text('last_sanitized_failure')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'capability_code']);
            $table->index(['api_family', 'implemented']);
            $table->index(['admin_enabled', 'public_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hbx_api_capabilities');
    }
};
