<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('internal_code')->unique();
            $table->unsignedTinyInteger('star_rating')->nullable()->index();
            $table->string('property_type', 32)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('primary_phone', 64)->nullable();
            $table->string('primary_email')->nullable();
            $table->string('website_url')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedInteger('total_rooms')->nullable();
            $table->unsignedSmallInteger('year_opened')->nullable();
            $table->unsignedSmallInteger('year_renovated')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'city_id', 'area_id']);
            $table->index(['status', 'is_active', 'is_featured']);
        });

        Schema::create('hotel_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2)->index();
            $table->string('translated_name');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->text('address_text')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'locale']);
        });

        Schema::create('hotel_facility', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'facility_id']);
        });

        Schema::create('hotel_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('contact_type', 32)->index();
            $table->string('department')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('mobile', 64)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hotel_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 64)->default('public');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('image_type', 32)->index();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'is_primary', 'is_active']);
        });

        Schema::create('hotel_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('check_in_from')->nullable();
            $table->time('check_in_until')->nullable();
            $table->time('check_out_from')->nullable();
            $table->time('check_out_until')->nullable();
            $table->text('children_policy')->nullable();
            $table->text('extra_bed_policy')->nullable();
            $table->text('pet_policy')->nullable();
            $table->text('smoking_policy')->nullable();
            $table->text('cancellation_notes')->nullable();
            $table->text('important_information')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_policies');
        Schema::dropIfExists('hotel_images');
        Schema::dropIfExists('hotel_contacts');
        Schema::dropIfExists('hotel_facility');
        Schema::dropIfExists('hotel_translations');
        Schema::dropIfExists('hotels');
    }
};
