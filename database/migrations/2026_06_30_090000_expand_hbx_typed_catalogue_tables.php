<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hbx_destinations', function (Blueprint $table): void {
            $table->dropUnique(['supplier_code', 'destination_code']);
            $table->string('content_language', 8)->default('ENG')->after('parent_destination_code');
            $table->string('destination_type', 64)->nullable()->after('content_language');
            $table->decimal('latitude', 10, 7)->nullable()->after('destination_type');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('supplier_active')->default(true)->after('longitude');
            $table->boolean('public_enabled')->default(false)->after('supplier_active');
            $table->string('name_ar')->nullable()->after('public_enabled');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('slug')->nullable()->after('name_en');
            $table->string('seo_title')->nullable()->after('slug');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->unsignedInteger('display_order')->default(100)->after('seo_description');
            $table->timestamp('last_supplier_update_at')->nullable()->after('display_order');
            $table->timestamp('last_synced_at')->nullable()->after('last_supplier_update_at');
            $table->string('payload_checksum', 128)->nullable()->after('last_synced_at');

            $table->unique(['supplier_code', 'destination_code', 'content_language'], 'hbx_destinations_supplier_code_language_unique');
            $table->unique(['supplier_code', 'slug', 'content_language'], 'hbx_destinations_supplier_slug_unique');
            $table->index(['supplier_code', 'supplier_active', 'public_enabled'], 'hbx_destinations_public_lookup');
            $table->index(['country_code', 'public_enabled']);
            $table->index('destination_name');
        });

        Schema::table('hbx_hotels', function (Blueprint $table): void {
            $table->string('country_code', 8)->nullable()->after('destination_code')->index();
            $table->string('zone_code', 32)->nullable()->after('country_code')->index();
            $table->string('postal_code', 32)->nullable()->after('address');
            $table->string('accommodation_type_code', 64)->nullable()->after('postal_code');
            $table->string('chain_code', 64)->nullable()->after('accommodation_type_code');
            $table->string('primary_phone')->nullable()->after('chain_code');
            $table->string('primary_email')->nullable()->after('primary_phone');
            $table->boolean('supplier_active')->default(true)->after('primary_email');
            $table->boolean('public_enabled')->default(false)->after('supplier_active');
            $table->string('name_ar')->nullable()->after('public_enabled');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('slug')->nullable()->after('name_en');
            $table->string('seo_title')->nullable()->after('slug');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->unsignedInteger('display_order')->default(100)->after('seo_description');
            $table->timestamp('last_supplier_update_at')->nullable()->after('display_order');
            $table->timestamp('last_synced_at')->nullable()->after('last_supplier_update_at');
            $table->string('payload_checksum', 128)->nullable()->after('last_synced_at');

            $table->unique(['supplier_code', 'slug'], 'hbx_hotels_supplier_slug_unique');
            $table->index(['supplier_code', 'supplier_active', 'public_enabled'], 'hbx_hotels_public_lookup');
            $table->index(['supplier_code', 'destination_code', 'public_enabled'], 'hbx_hotels_destination_public_lookup');
            $table->index('hotel_name');
        });

        Schema::create('hbx_destination_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 64)->default('hbx_hotels');
            $table->string('destination_code', 32);
            $table->string('zone_code', 32);
            $table->string('zone_name')->nullable();
            $table->string('content_language', 8)->default('ENG');
            $table->json('payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'destination_code', 'zone_code', 'content_language'], 'hbx_destination_zones_unique');
            $table->index(['supplier_code', 'destination_code', 'is_active'], 'hbx_destination_zones_lookup');
        });

        Schema::create('hbx_hotel_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hbx_hotel_id')->constrained('hbx_hotels')->cascadeOnDelete();
            $table->string('language', 8);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->unique(['hbx_hotel_id', 'language']);
        });

        Schema::create('hbx_hotel_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hbx_hotel_id')->constrained('hbx_hotels')->cascadeOnDelete();
            $table->string('image_type_code', 64)->nullable();
            $table->string('path');
            $table->string('room_code', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['hbx_hotel_id', 'path']);
            $table->index(['hbx_hotel_id', 'is_primary', 'is_active']);
        });

        Schema::create('hbx_hotel_facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hbx_hotel_id')->constrained('hbx_hotels')->cascadeOnDelete();
            $table->string('facility_code', 64);
            $table->string('facility_group_code', 64)->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['hbx_hotel_id', 'facility_code']);
        });

        Schema::create('hbx_hotel_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hbx_hotel_id')->constrained('hbx_hotels')->cascadeOnDelete();
            $table->string('room_code', 64);
            $table->string('room_name')->nullable();
            $table->string('characteristic_code', 64)->nullable();
            $table->unsignedInteger('min_adults')->nullable();
            $table->unsignedInteger('max_adults')->nullable();
            $table->unsignedInteger('max_children')->nullable();
            $table->unsignedInteger('max_pax')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['hbx_hotel_id', 'room_code', 'characteristic_code'], 'hbx_hotel_rooms_code_char_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hbx_hotel_rooms');
        Schema::dropIfExists('hbx_hotel_facilities');
        Schema::dropIfExists('hbx_hotel_images');
        Schema::dropIfExists('hbx_hotel_translations');
        Schema::dropIfExists('hbx_destination_zones');

        Schema::table('hbx_hotels', function (Blueprint $table): void {
            $table->dropUnique('hbx_hotels_supplier_slug_unique');
            $table->dropIndex('hbx_hotels_public_lookup');
            $table->dropIndex('hbx_hotels_destination_public_lookup');
            $table->dropIndex(['country_code']);
            $table->dropIndex(['zone_code']);
            $table->dropIndex(['hotel_name']);
            $table->dropColumn([
                'country_code',
                'zone_code',
                'postal_code',
                'accommodation_type_code',
                'chain_code',
                'primary_phone',
                'primary_email',
                'supplier_active',
                'public_enabled',
                'name_ar',
                'name_en',
                'slug',
                'seo_title',
                'seo_description',
                'display_order',
                'last_supplier_update_at',
                'last_synced_at',
                'payload_checksum',
            ]);
        });

        Schema::table('hbx_destinations', function (Blueprint $table): void {
            $table->dropUnique('hbx_destinations_supplier_code_language_unique');
            $table->dropUnique('hbx_destinations_supplier_slug_unique');
            $table->dropIndex('hbx_destinations_public_lookup');
            $table->dropIndex(['country_code', 'public_enabled']);
            $table->dropIndex(['destination_name']);
            $table->dropColumn([
                'content_language',
                'destination_type',
                'latitude',
                'longitude',
                'supplier_active',
                'public_enabled',
                'name_ar',
                'name_en',
                'slug',
                'seo_title',
                'seo_description',
                'display_order',
                'last_supplier_update_at',
                'last_synced_at',
                'payload_checksum',
            ]);
            $table->unique(['supplier_code', 'destination_code']);
        });
    }
};
