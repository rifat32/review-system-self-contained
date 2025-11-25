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
            $table->unsignedBigInteger('guest_survey_id')->nullable();
            $table->unsignedBigInteger('registered_user_survey_id')->nullable();
            $table->foreign('guest_survey_id')->references('id')->on('surveys')->onDelete('set null');
            $table->foreign('registered_user_survey_id')->references('id')->on('surveys')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['guest_survey_id']);
            $table->dropForeign(['registered_user_survey_id']);
            $table->dropColumn('guest_survey_id');
            $table->dropColumn('registered_user_survey_id');
        });
    }
};
