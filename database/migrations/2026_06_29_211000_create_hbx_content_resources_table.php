<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hbx_content_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 64)->default('hbx_hotels');
            $table->string('resource_type', 80);
            $table->string('resource_code', 160);
            $table->string('language', 8)->default('ENG');
            $table->string('name')->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('destination_code', 32)->nullable()->index();
            $table->string('parent_code', 160)->nullable();
            $table->json('payload')->nullable();
            $table->string('payload_hash', 128)->nullable();
            $table->timestamp('last_update_time')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'resource_type', 'resource_code', 'language'], 'hbx_content_unique');
            $table->index(['resource_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hbx_content_resources');
    }
};
