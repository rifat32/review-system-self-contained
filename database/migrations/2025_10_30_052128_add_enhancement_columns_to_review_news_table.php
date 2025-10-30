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
           
               $table->string('review_type')->nullable();
    $table->enum('sentiment', ['positive','neutral','negative'])->nullable();
    $table->boolean('verified')->default(false);
    $table->unsignedBigInteger('topic_id')->nullable();
    $table->text('reply_content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
        $table->dropColumn([
            'source',
            'language',
            'responded_at',
            'review_type',
            'sentiment',
            'verified',
            'topic_id',
            'reply_content',
        ]);
    });
    }
























}
