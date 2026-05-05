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
        Schema::create('review_rule_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained('review_news')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // The 9 Default Rule Outcomes
            $table->boolean('is_flagged')->default(false);              // Master Flag
            $table->boolean('is_sentiment_flagged')->default(false);    // Rule 1
            $table->boolean('is_high_emotion')->default(false);         // Rule 2
            $table->boolean('is_mismatch')->default(false);             // Rule 3
            $table->boolean('is_category_detected')->default(false);    // Rule 4
            $table->boolean('is_service_identified')->default(false);   // Rule 5
            $table->boolean('is_area_detected')->default(false);        // Rule 6
            $table->boolean('is_staff_mentioned')->default(false);      // Rule 7
            $table->boolean('is_staff_risk')->default(false);           // Rule 8
            $table->boolean('is_critical_alert')->default(false);       // Rule 9

            // Custom Rule Tracking
            $table->boolean('is_custom_rule_triggered')->default(false);
            $table->json('triggered_custom_rule_ids')->nullable();
            
            // Intelligence & Summary Fields
            $table->string('highest_priority')->nullable(); // critical, high, medium, low
            $table->integer('total_rules_matched')->default(0);
            $table->json('execution_summary')->nullable(); // Snapshot of reasons/matches
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_rule_outcomes');
    }
};
