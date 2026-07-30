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
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('rolling_ai_insight')->nullable();
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->boolean('is_rolling_aggregated')->default(false);
            $table->index(['business_id', 'is_ai_processed', 'is_rolling_aggregated', 'id'], 'reviews_rolling_agg_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['rolling_ai_insight']);
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->dropIndex('reviews_rolling_agg_idx');
            $table->dropColumn(['is_rolling_aggregated']);
        });
    }
};
