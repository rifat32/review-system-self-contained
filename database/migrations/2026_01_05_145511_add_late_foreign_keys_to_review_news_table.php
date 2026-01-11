<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Check if foreign keys already exist using raw SQL
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'review_news' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'");

        $existingKeys = collect($foreignKeys)->pluck('CONSTRAINT_NAME')->toArray();

        Schema::table('review_news', function (Blueprint $table) use ($existingKeys) {
            if (!in_array('review_news_survey_id_foreign', $existingKeys)) {
                $table->foreign('survey_id')
                    ->references('id')
                    ->on('surveys')
                    ->nullOnDelete();
            }

            if (!in_array('review_news_branch_id_foreign', $existingKeys)) {
                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropForeign(['branch_id']);
        });
    }

};
