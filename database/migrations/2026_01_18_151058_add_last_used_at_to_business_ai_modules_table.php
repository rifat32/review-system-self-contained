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
        Schema::table('business_ai_modules', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('total_cost_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_ai_modules', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
