<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->decimal('sentiment_score', 3, 2)->nullable()->after('emotion');
            $table->json('topics')->nullable()->after('key_phrases');
            $table->json('moderation_results')->nullable()->after('topics');
            $table->json('ai_suggestions')->nullable()->after('moderation_results');
            $table->json('staff_suggestions')->nullable()->after('ai_suggestions');
        });
    }

    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn([
                'sentiment_score',
                'topics', 
                'moderation_results',
                'ai_suggestions',
                'staff_suggestions'
            ]);
        });
    }
};
