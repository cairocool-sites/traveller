<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hbx_destinations', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 64);
            $table->string('destination_code', 32);
            $table->string('destination_name');
            $table->string('country_code', 8)->nullable();
            $table->string('parent_destination_code', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'destination_code']);
            $table->index(['country_code', 'is_active']);
            $table->index(['supplier_code', 'is_active']);
        });

        Schema::create('hbx_hotels', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 64);
            $table->string('hotel_code', 32);
            $table->string('destination_code', 32)->index();
            $table->string('hotel_name');
            $table->string('category_code', 32)->nullable();
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'hotel_code']);
            $table->index(['supplier_code', 'destination_code', 'is_active']);
            $table->index(['destination_code', 'is_active']);
        });

        Schema::create('supplier_destination_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('local_entity_type', 32);
            $table->unsignedBigInteger('local_entity_id');
            $table->string('supplier_code', 64);
            $table->string('supplier_destination_code', 32);
            $table->string('status', 32)->default('suggested');
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->boolean('manually_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['local_entity_type', 'local_entity_id', 'supplier_code', 'supplier_destination_code'], 'supplier_destination_mapping_unique');
            $table->index(['local_entity_type', 'local_entity_id', 'supplier_code', 'is_active'], 'supplier_destination_mapping_lookup');
            $table->index(['supplier_code', 'supplier_destination_code', 'is_active'], 'supplier_destination_mapping_supplier_lookup');
        });

        Schema::create('supplier_hotel_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_code', 64);
            $table->string('supplier_hotel_code', 32);
            $table->string('status', 32)->default('suggested');
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->boolean('manually_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'supplier_code', 'supplier_hotel_code']);
            $table->index(['supplier_code', 'supplier_hotel_code', 'is_active']);
            $table->index(['hotel_id', 'supplier_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_hotel_mappings');
        Schema::dropIfExists('supplier_destination_mappings');
        Schema::dropIfExists('hbx_hotels');
        Schema::dropIfExists('hbx_destinations');
    }
};
