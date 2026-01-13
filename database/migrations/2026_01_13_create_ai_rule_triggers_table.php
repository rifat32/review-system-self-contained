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
        Schema::create('ai_rule_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id', 100);
            $table->foreignId('review_id')->constrained('review_news')->onDelete('cascade');
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->decimal('confidence_score', 5, 2); // Confidence percentage (0-100)
            $table->json('matched_conditions'); // Which conditions matched
            $table->json('actions_triggered'); // Which actions were executed
            $table->enum('outcome', ['true_positive', 'false_positive', 'pending'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->index(['rule_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index(['outcome']);

            $table->foreign('rule_id')->references('rule_id')->on('ai_rules')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_rule_triggers');
    }
};
