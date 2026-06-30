<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('instructions_ar');
            $table->text('instructions_en');
            $table->string('account_name')->nullable();
            $table->string('account_reference')->nullable();
            $table->boolean('supports_attachment')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('manual_payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->index();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('submitted_reference')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('payment_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('issued');
            $table->json('snapshot');
            $table->string('verification_token', 80)->unique();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('issued_at')->useCurrent();
            $table->string('status', 30)->default('issued');
            $table->json('snapshot');
            $table->string('verification_token', 80)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->json('payment_method_snapshot');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->string('status', 30)->default('issued');
            $table->json('snapshot');
            $table->string('verification_token', 80)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('payment_evidences');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('manual_payment_methods');
    }
};
