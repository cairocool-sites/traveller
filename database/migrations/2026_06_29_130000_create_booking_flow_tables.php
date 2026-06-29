<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('search_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->text('supplier_hotel_reference');
            $table->text('supplier_rate_reference');
            $table->text('supplier_room_reference')->nullable();
            $table->unsignedBigInteger('original_amount_minor');
            $table->unsignedBigInteger('checked_amount_minor')->nullable();
            $table->boolean('price_changed')->default(false);
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->json('room_snapshot');
            $table->json('occupancy_snapshot');
            $table->json('supplier_reference_snapshot')->nullable();
            $table->uuid('correlation_id');
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('expires_at');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('booking_reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('search_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 60)->index();
            $table->string('payment_status', 40)->default('not_required')->index();
            $table->string('locale', 5)->default('ar');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('rooms_count');
            $table->unsignedTinyInteger('adults_count');
            $table->unsignedTinyInteger('children_count');
            $table->string('supplier_booking_reference')->nullable()->index();
            $table->string('supplier_confirmation_reference')->nullable();
            $table->string('supplier_status')->nullable();
            $table->unsignedBigInteger('total_amount_minor');
            $table->unsignedBigInteger('net_amount_minor')->nullable();
            $table->unsignedBigInteger('taxes_amount_minor')->nullable();
            $table->unsignedBigInteger('fees_amount_minor')->nullable();
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->json('hotel_snapshot');
            $table->json('room_snapshot');
            $table->json('occupancy_snapshot');
            $table->json('supplier_response_snapshot')->nullable();
            $table->uuid('correlation_id');
            $table->string('idempotency_key')->unique();
            $table->string('idempotency_payload_hash', 64);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('special_requests')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('booking_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room_index');
            $table->string('room_name');
            $table->string('board_basis', 60)->nullable();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->json('child_ages')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->text('supplier_room_reference')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'room_index']);
        });

        Schema::create('booking_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->foreignId('nationality_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->boolean('is_lead_guest')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['booking_id', 'type']);
        });

        Schema::create('booking_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('booking_guests');
        Schema::dropIfExists('booking_rooms');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('rate_checks');
    }
};
