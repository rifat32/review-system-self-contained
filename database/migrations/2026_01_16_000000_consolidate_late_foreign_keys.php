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
            $table->foreign('default_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('guest_survey_id')->references('id')->on('surveys')->nullOnDelete();
            $table->foreign('registered_user_survey_id')->references('id')->on('surveys')->nullOnDelete();
        });

        // Review News Late Foreign Keys
        Schema::table('review_news', function (Blueprint $table) {
            $table->foreign('survey_id')->references('id')->on('surveys')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
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
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropForeign(['branch_id']);
        });
    }
};
