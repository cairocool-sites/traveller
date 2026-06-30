<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('destination_type', 32);
            $table->unsignedBigInteger('destination_id');
            $table->string('destination_label');
            $table->date('check_in');
            $table->date('check_out');
            $table->json('occupancy');
            $table->string('nationality', 2)->nullable();
            $table->string('residency_country', 2)->nullable();
            $table->string('currency', 3);
            $table->string('locale', 2);
            $table->string('anonymous_session_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('correlation_id')->index();
            $table->json('criteria_snapshot');
            $table->json('results_snapshot')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['destination_type', 'destination_id']);
            $table->index(['currency', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_sessions');
    }
};
