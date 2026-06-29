<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hbx_content_sync_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('resource', 64)->index();
            $table->string('mode', 32)->default('bounded')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('destination_code', 32)->nullable()->index();
            $table->string('language', 8)->default('ENG');
            $table->unsignedInteger('page_limit')->default(1);
            $table->string('last_update_time')->nullable();
            $table->json('checkpoint')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('stored_count')->default(0);
            $table->text('error_message')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->boolean('full_authorized_portfolio')->default(false);
            $table->boolean('queued')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['resource', 'status', 'created_at']);
            $table->index(['country_code', 'resource', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hbx_content_sync_batches');
    }
};
