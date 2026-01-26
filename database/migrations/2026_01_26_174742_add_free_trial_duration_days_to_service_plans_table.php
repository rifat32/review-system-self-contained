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
        Schema::table('service_plans', function (Blueprint $table) {
            $table->integer('free_trial_duration_date')->default(30)->after('openai_token_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_plans', function (Blueprint $table) {
            $table->dropColumn('free_trial_duration_date');
        });
    }
};
