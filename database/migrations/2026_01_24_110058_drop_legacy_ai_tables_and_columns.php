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
        Schema::dropIfExists('rating_mismatches');
        Schema::dropIfExists('review_emotions');
        Schema::dropIfExists('tag_reviews');

        if (Schema::hasColumn('review_news', 'topic_id')) {
            Schema::table('review_news', function (Blueprint $table) {
                $table->dropColumn('topic_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->unsignedBigInteger('topic_id')->nullable()->after('review_type');
        });

        Schema::create('tag_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
        });

        Schema::create('review_emotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->string('emotion');
            $table->float('intensity_score');
            $table->string('intensity_level');
            $table->float('confidence');
            $table->json('keywords_matched')->nullable();
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('review_news')->onDelete('cascade');
        });

        Schema::create('rating_mismatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('business_id');
            $table->string('mismatch_type');
            $table->string('severity');
            $table->float('rating');
            $table->string('detected_sentiment');
            $table->float('sentiment_score');
            $table->text('explanation');
            $table->enum('status', ['pending', 'reviewed', 'ignored'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('review_news')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
