<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            if (!Schema::hasColumn('review_news', 'ai_confidence')) {
                $table->decimal('ai_confidence', 3, 2)->nullable()->after('is_ai_processed')->comment('Confidence score 0.00-1.00');
            }
            if (!Schema::hasColumn('review_news', 'sentiment_label')) {
                $table->string('sentiment_label', 20)->nullable()->after('sentiment_score')->comment('very_negative, negative, neutral, positive, very_positive');
            }
            if (!Schema::hasColumn('review_news', 'openai_raw_response')) {
                $table->json('openai_raw_response')->nullable()->after('staff_suggestions');
            }
            if (!Schema::hasColumn('review_news', 'is_abusive')) {
                $table->boolean('is_abusive')->default(false)->after('language');
            }
            if (!Schema::hasColumn('review_news', 'summary')) {
                $table->text('summary')->nullable()->after('is_abusive');
            }
            if (!Schema::hasColumn('review_news', 'service_analysis')) {
                $table->json('service_analysis')->nullable()->after('summary');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $columns = ['ai_confidence', 'sentiment_label', 'openai_raw_response', 'is_abusive', 'summary', 'service_analysis'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('review_news', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
