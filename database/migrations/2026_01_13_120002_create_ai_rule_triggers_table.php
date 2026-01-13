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
            $table->foreignId('review_id')->constrained('review_news')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // DEDUPLICATION & SUPPRESSION FIELDS
            $table->string('dedup_key', 255)->nullable();
            $table->boolean('was_suppressed')->default(false);
            $table->string('suppressed_reason', 500)->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 100)->nullable();

            // Trigger details
            $table->float('confidence_score')->default(0);
            $table->json('matched_conditions')->nullable();
            $table->json('actions_triggered')->nullable();
            $table->enum('outcome', ['pending', 'true_positive', 'false_positive'])->default('pending');

            // Verification fields
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('rule_id');
            $table->index(['dedup_key', 'created_at'], 'idx_dedup_cooldown');
            $table->index(['rule_id', 'was_suppressed', 'created_at'], 'idx_rule_suppression');
            $table->index(['staff_id', 'created_at'], 'idx_staff_triggers');
            $table->index(['business_id', 'created_at']);
            $table->index('outcome');

            // Foreign key for rule
            $table->foreign('rule_id')->references('rule_id')->on('ai_rules')->cascadeOnDelete();
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
