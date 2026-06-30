<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->string('integration_type', 32)->index();
            $table->string('environment', 32)->default('sandbox')->index();
            $table->string('status', 32)->default('inactive')->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->unsignedInteger('connect_timeout_seconds')->default(5);
            $table->unsignedInteger('max_retries')->default(0);
            $table->unsignedInteger('retry_delay_milliseconds')->default(250);
            $table->boolean('search_enabled')->default(false)->index();
            $table->boolean('details_enabled')->default(false);
            $table->boolean('check_rate_enabled')->default(false);
            $table->boolean('booking_enabled')->default(false);
            $table->boolean('cancellation_enabled')->default(false);
            $table->boolean('booking_lookup_enabled')->default(false);
            $table->boolean('health_check_enabled')->default(false);
            $table->string('base_url')->nullable();
            $table->string('health_status', 32)->default('unknown')->index();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['integration_type', 'environment']);
        });

        Schema::create('supplier_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('credential_key', 128);
            $table->text('encrypted_value')->nullable();
            $table->boolean('is_secret')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'credential_key']);
        });

        Schema::create('supplier_operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('correlation_id')->index();
            $table->string('operation', 32)->index();
            $table->string('request_method', 16)->nullable();
            $table->string('request_url')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->boolean('successful')->default(false)->index();
            $table->string('error_type', 64)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->string('booking_reference')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['supplier_id', 'operation', 'created_at']);
        });

        Schema::create('supplier_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 32);
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 128);
            $table->json('response_snapshot')->nullable();
            $table->string('status', 32)->default('completed')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['supplier_id', 'operation', 'idempotency_key'],
                'supplier_idempotency_op_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_idempotency_records');
        Schema::dropIfExists('supplier_operation_logs');
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('suppliers');
    }
};
