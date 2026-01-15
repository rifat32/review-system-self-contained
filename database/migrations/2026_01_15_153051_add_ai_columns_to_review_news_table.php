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
        Schema::table('review_news', function (Blueprint $table) {
            if (!Schema::hasColumn('review_news', 'ai_insights')) {
                $table->json('ai_insights')->nullable()->after('openai_raw_response');
            }
            if (!Schema::hasColumn('review_news', 'ai_recommendations')) {
                $table->json('ai_recommendations')->nullable()->after('ai_insights');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn(['ai_insights', 'ai_recommendations']);
        });
    }
};
