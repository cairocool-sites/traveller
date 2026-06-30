<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_certification_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('operation_type', 80)->index();
            $table->string('local_reference')->index();
            $table->string('supplier_reference')->nullable()->index();
            $table->string('supplier_status')->nullable();
            $table->string('summary_status', 40)->index();
            $table->json('field_results')->nullable();
            $table->json('sanitized_snapshot')->nullable();
            $table->json('voucher_evidence')->nullable();
            $table->json('cancellation_simulation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'operation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_certification_evidences');
    }
};
