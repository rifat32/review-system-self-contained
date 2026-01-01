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
            $table->boolean('rating_comment_mismatch')->default(false)->after('sentiment_label');
            $table->json('mismatch_insights')->nullable()->after('rating_comment_mismatch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn(['rating_comment_mismatch', 'mismatch_insights']);
        });
    }
};
