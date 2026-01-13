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
        Schema::create('review_emotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('review_news')->onDelete('cascade');
            $table->string('emotion', 50); // joy, anger, frustration, satisfaction, disappointment
            $table->decimal('intensity_score', 3, 2); // 0.00 to 1.00
            $table->enum('intensity_level', ['low', 'medium', 'high']);
            $table->decimal('confidence', 3, 2)->nullable(); // AI confidence score
            $table->json('keywords_matched')->nullable(); // Keywords that triggered detection
            $table->timestamps();

            $table->index(['review_id', 'emotion']);
            $table->index(['emotion', 'intensity_level']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_emotions');
    }
};
