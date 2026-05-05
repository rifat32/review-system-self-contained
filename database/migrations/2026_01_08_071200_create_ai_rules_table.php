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
        Schema::create('ai_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id', 100)->unique();
            $table->string('rule_name', 255);
            $table->text('description')->nullable();

            $table->string('key_name')->nullable();
            $table->text('value')->nullable();

            $table->enum('scope', ['business', 'system'])->default('business');
            $table->string('business_type')->nullable();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->string('category', 100)->nullable();
            $table->string('priority', 50)->default('medium');
            $table->boolean('enabled')->default(true);

            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->json('explainability')->nullable();

            // UI & Visualization fields
            $table->float('precision_rate')->nullable();
            $table->integer('lifetime_triggers')->default(0);
            $table->json('branch_ids')->nullable();
            $table->boolean('multi_tag_detection')->default(false);
            $table->boolean('trigger_only_on_first_occurrence')->default(false);
            $table->enum('applies_to', ['new_reviews_only', 'all_reviews'])->default('new_reviews_only');

            // Recipient for email notifications
            $table->string('recipient')->nullable();

            // AI Explanation fields (Legacy/UI specific)
            $table->text('ai_explanation_title')->nullable();
            $table->text('ai_plain_explanation')->nullable();
            $table->text('ai_why_it_matters')->nullable();
            $table->text('ai_when_it_triggers')->nullable();
            $table->text('ai_manager_tip')->nullable();
            $table->timestamp('ai_generated_at')->nullable();

            // Explanation fields
            $table->text('short_explanation')->nullable();
            $table->text('detailed_explanation')->nullable();
            $table->text('why_it_matters')->nullable();
            $table->timestamp('explanation_generated_at')->nullable();

            // EXECUTION CONTROL FIELDS
            $table->enum('run_frequency', ['real_time', 'hourly', 'daily', 'weekly'])->default('daily');
            $table->integer('cooldown_days')->default(7)->comment('Minimum days between same issue alerts');
            $table->enum('deduplication_scope', ['review', 'staff', 'category', 'branch', 'staff_category'])
                ->default('staff')
                ->comment('Defines what counts as same issue');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            $table->string('created_by', 50)->default('system');
            $table->boolean('is_default')->default(false)
                ->comment('True for system-owned default rules, false for user-created custom rules');
            $table->integer('version')->default(1);
            $table->timestamps();

            // Indexes
            $table->index(['scope', 'business_type', 'business_id']);
            $table->index(['category', 'enabled']);
            $table->index(['enabled', 'run_frequency', 'next_run_at'], 'idx_rule_scheduling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_rules');
    }
};
