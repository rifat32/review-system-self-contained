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
        Schema::table('users', function (Blueprint $table) {
            $table->index('business_id');
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->index('status');
            $table->index('is_flagged');
            $table->index('is_ai_processed');
            $table->index('created_at');
            $table->index(['business_id', 'branch_id', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('ai_rules', function (Blueprint $table) {
            $table->index('business_id');
            $table->index(['enabled', 'category']);

            // Add composite index for fast default rule queries
            $table->index(['is_default', 'business_id', 'enabled'], 'idx_default_rules_lookup');

            // Add index for custom rule queries (notifications)
            $table->index(['is_default', 'enabled', 'run_frequency'], 'idx_custom_rules_scheduling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['business_id']);
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['is_flagged']);
            $table->dropIndex(['is_ai_processed']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['business_id', 'branch_id', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('ai_rules', function (Blueprint $table) {
            $table->dropIndex(['business_id']);
            $table->dropIndex(['enabled', 'category']);
            $table->dropIndex('idx_default_rules_lookup');
            $table->dropIndex('idx_custom_rules_scheduling');
        });
    }
};
