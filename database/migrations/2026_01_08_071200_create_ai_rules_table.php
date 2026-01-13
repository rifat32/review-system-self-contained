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

            $table->enum('scope', ['system', 'business_type', 'business']);
            $table->string('business_type', 50)->nullable();
            $table->unsignedBigInteger('business_id')->nullable();

            $table->string('category', 50);
            $table->string('priority', 20)->default('medium');
            $table->boolean('enabled')->default(true);

            $table->json('conditions');
            $table->json('actions');
            $table->json('explainability')->nullable();

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
            $table->integer('version')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('business_id')->references('id')->on('businesses');

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
