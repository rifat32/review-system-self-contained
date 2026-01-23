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
        // Businesses Late Foreign Keys
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'default_branch_id')) {
                $table->unsignedBigInteger('default_branch_id')->nullable();
            }

            // Check if foreign keys exist before adding
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'businesses' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
            $existingKeys = array_column($foreignKeys, 'CONSTRAINT_NAME');

            if (!in_array('businesses_default_branch_id_foreign', $existingKeys)) {
                $table->foreign('default_branch_id')->references('id')->on('branches')->nullOnDelete();
            }
            if (!in_array('businesses_guest_survey_id_foreign', $existingKeys)) {
                $table->foreign('guest_survey_id')->references('id')->on('surveys')->nullOnDelete();
            }
            if (!in_array('businesses_registered_user_survey_id_foreign', $existingKeys)) {
                $table->foreign('registered_user_survey_id')->references('id')->on('surveys')->nullOnDelete();
            }
            if (!in_array('businesses_service_plan_id_foreign', $existingKeys)) {
                $table->foreign('service_plan_id')->references('id')->on('service_plans')->nullOnDelete();
            }
        });

        // Review News Late Foreign Keys
        Schema::table('review_news', function (Blueprint $table) {
            $table->foreign('survey_id')->references('id')->on('surveys')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['default_branch_id']);
            $table->dropForeign(['guest_survey_id']);
            $table->dropForeign(['registered_user_survey_id']);
            $table->dropColumn(['default_branch_id', 'guest_survey_id', 'registered_user_survey_id']);
            $table->dropForeign(['service_plan_id']);
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropForeign(['branch_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
        });
    }
};
