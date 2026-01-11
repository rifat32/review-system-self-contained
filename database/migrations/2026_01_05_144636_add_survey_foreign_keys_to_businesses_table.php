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
            AND TABLE_NAME = 'businesses' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'");

        $existingKeys = collect($foreignKeys)->pluck('CONSTRAINT_NAME')->toArray();

        Schema::table('businesses', function (Blueprint $table) use ($existingKeys) {
            if (!in_array('businesses_guest_survey_id_foreign', $existingKeys)) {
                $table->foreign('guest_survey_id')
                    ->references('id')
                    ->on('surveys')
                    ->nullOnDelete();
            }

            if (!in_array('businesses_registered_user_survey_id_foreign', $existingKeys)) {
                $table->foreign('registered_user_survey_id')
                    ->references('id')
                    ->on('surveys')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['guest_survey_id']);
            $table->dropForeign(['registered_user_survey_id']);
        });
    }

};
