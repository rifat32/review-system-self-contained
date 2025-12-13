<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->string('sentiment_label')->nullable()->after('raw_text');
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn('sentiment_label');
        });
    }
};
