<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password')->index();
            $table->string('preferred_locale', 2)->default('ar')->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['preferred_locale']);
            $table->dropColumn(['is_active', 'preferred_locale']);
        });
    }
};
