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
        Schema::create('rating_mismatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('review_news')->onDelete('cascade');
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->enum('mismatch_type', [
                'high_rating_negative_comment',
                'low_rating_positive_comment',
                'neutral_rating_extreme_sentiment'
            ]);
            $table->enum('severity', ['low', 'medium', 'high']);
            $table->decimal('rating', 2, 1); // The star rating
            $table->string('detected_sentiment', 50);
            $table->decimal('sentiment_score', 3, 2);
            $table->text('explanation');
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['mismatch_type', 'severity']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_mismatches');
    }
};
