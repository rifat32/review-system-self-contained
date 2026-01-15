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
        Schema::table('businesses', function (Blueprint $table) {
            // Add is_active column with default value true
            $table->boolean('is_active')->default(true)->after('id');

            // Add last_recommendation_at column for tracking
            $table->timestamp('last_recommendation_at')->nullable()->after('updated_at');

            // Add indexes for better query performance
            $table->index('is_active');
            $table->index('last_recommendation_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Remove indexes first
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_recommendation_at']);

            // Remove the columns
            $table->dropColumn(['is_active', 'last_recommendation_at']);
        });
    }
};
