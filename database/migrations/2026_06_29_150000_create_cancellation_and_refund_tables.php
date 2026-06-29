<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('customer_reason')->nullable();
            $table->text('internal_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier_cancellation_reference')->nullable();
            $table->string('supplier_status')->nullable();
            $table->unsignedBigInteger('penalty_amount_minor')->default(0);
            $table->unsignedBigInteger('refundable_amount_minor')->default(0);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->json('supplier_response_snapshot')->nullable();
            $table->uuid('correlation_id');
            $table->string('idempotency_key')->unique();
            $table->string('idempotency_payload_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_cancellation_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->unsignedBigInteger('refunded_amount_minor')->nullable();
            $table->string('method')->default('manual');
            $table->string('external_reference')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cancellation_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_cancellation_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refund_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_status_histories');
        Schema::dropIfExists('cancellation_status_histories');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('booking_cancellations');
    }
};
