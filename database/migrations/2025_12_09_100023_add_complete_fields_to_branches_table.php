<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->boolean('is_geo_enabled')->default(false);
            $table->string('branch_code')->nullable();
            $table->string('lat', 10)->nullable();
            $table->string('long', 11)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_geo_enabled', 'branch_code', 'lat', 'long']);
        });
    }
};
