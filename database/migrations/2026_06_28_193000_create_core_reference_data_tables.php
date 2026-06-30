<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->unique();
            $table->string('numeric_code', 3)->nullable()->index();
            $table->string('phone_code', 12)->nullable();
            $table->string('name_en')->index();
            $table->string('name_ar')->index();
            $table->string('nationality_en')->nullable();
            $table->string('nationality_ar')->nullable();
            $table->string('currency_code', 3)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('code')->nullable()->index();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['country_id', 'name_en']);
            $table->unique(['country_id', 'name_ar']);
            $table->index(['country_id', 'is_active', 'is_featured']);
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['city_id', 'name_en']);
            $table->unique(['city_id', 'name_ar']);
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('numeric_code', 3)->nullable()->index();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->decimal('rounding_increment', 12, 6)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_base')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('rate', 20, 10);
            $table->string('source', 64)->default('manual')->index();
            $table->dateTime('effective_at')->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['base_currency_id', 'quote_currency_id', 'effective_at', 'is_active'],
                'exchange_rates_pair_effective_active_unique'
            );
            $table->index(
                ['base_currency_id', 'quote_currency_id', 'is_active', 'effective_at'],
                'exchange_rates_pair_active_effective_index'
            );
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('icon')->nullable();
            $table->string('category', 32)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('facility_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_translations');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
