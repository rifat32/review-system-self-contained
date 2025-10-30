<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnhancementColumnsToReviewNewsTable extends Migration
{

    
     public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->string('source')->nullable()->after('id'); // e.g., website, mobile, qr_code
            $table->string('language')->nullable()->after('source'); // e.g., en, de
            $table->timestamp('responded_at')->nullable()->after('language'); // when business responded
            $table->tinyInteger('sentiment')->nullable()->comment('1=positive, 0=neutral, -1=negative')->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn(['source', 'language', 'responded_at', 'sentiment']);
        });
    }
























}
