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
        if (Schema::hasTable('business_ai_summaries') && !Schema::hasColumn('business_ai_summaries', 'trend_reasons')) {
            Schema::table('business_ai_summaries', function (Blueprint $table) {
                $table->json('trend_reasons')->nullable()->after('trend');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('business_ai_summaries') && Schema::hasColumn('business_ai_summaries', 'trend_reasons')) {
            Schema::table('business_ai_summaries', function (Blueprint $table) {
                $table->dropColumn('trend_reasons');
            });
        }
    }
};
