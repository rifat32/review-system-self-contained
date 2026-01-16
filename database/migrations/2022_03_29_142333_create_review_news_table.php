<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('review_news', function (Blueprint $table) {
            $table->id();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');


            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->text('comment')->nullable();

            // Added fields from alterations
            $table->string('source')->nullable();
            $table->string('language')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('review_type')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->text('reply_content')->nullable();
            $table->text('raw_text')->nullable();
            $table->string('emotion')->nullable();
            $table->json('key_phrases')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('is_overall')->default(false);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['pending', 'published', 'rejected'])->default('pending');
            $table->boolean('is_flagged')->default(false);
            $table->integer('order_no')->default(0);
            $table->float('sentiment_score')->nullable();
            $table->json('topics')->nullable();
            $table->json('moderation_results')->nullable();
            $table->json('ai_suggestions')->nullable();
            $table->json('staff_suggestions')->nullable();
            $table->unsignedBigInteger('survey_id')->nullable();
            $table->boolean('is_voice_review')->default(false);
            $table->string('voice_url')->nullable();
            $table->integer('voice_duration')->nullable();
            $table->json('transcription_metadata')->nullable();
            $table->boolean('is_private')->nullable();

            $table->boolean('rating_comment_mismatch')->default(false);
            $table->json('mismatch_insights')->nullable();


            $table->decimal('ai_confidence', 3, 2)->nullable()->comment('Confidence score 0.00-1.00');
            $table->string('sentiment_label', 20)->nullable()->comment('very_negative, negative, neutral, positive, very_positive');
            $table->json('openai_raw_response')->nullable();
            $table->json('ai_insights')->nullable();
            $table->json('ai_recommendations')->nullable();
            $table->boolean('is_abusive')->default(false);
            $table->text('summary')->nullable();
            $table->json('service_analysis')->nullable();

            $table->boolean('is_ai_processed')->default(0);

            $table->unsignedBigInteger('branch_id')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('review_news');
    }
};
