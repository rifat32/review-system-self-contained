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
        Schema::create('ai_rule_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id', 100)->unique();
            $table->integer('lifetime_triggers')->default(0);
            $table->integer('true_positives')->default(0);
            $table->integer('false_positives')->default(0);
            $table->integer('pending_verification')->default(0);
            $table->decimal('precision_rate', 5, 2)->nullable(); // Percentage (0-100)
            $table->integer('reviews_flagged')->default(0);
            $table->integer('coaching_actions')->default(0);
            $table->integer('escalations')->default(0);
            $table->integer('notifications_sent')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('rule_id')->references('rule_id')->on('ai_rules')->onDelete('cascade');
            $table->index(['precision_rate']);
            $table->index(['last_triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_rule_metrics');
    }
};
