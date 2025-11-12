<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiFieldsToReviewNewsTable extends Migration
{
    public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->text('raw_text')->nullable()->after('description'); // raw transcribed text
            $table->string('emotion')->nullable()->after('rate'); // sentiment/emotion
            $table->json('key_phrases')->nullable()->after('emotion'); // extracted key phrases
            $table->string('ip_address')->nullable()->after('key_phrases');
            $table->boolean('is_overall')->default(0)->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn(['raw_text', 'emotion', 'key_phrases']);
        });
    }
}
