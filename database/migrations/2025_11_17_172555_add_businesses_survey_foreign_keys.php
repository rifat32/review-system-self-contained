<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
    
    public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreign('guest_survey_id')->references('id')->on('surveys')->onDelete('set null');
            $table->foreign('registered_user_survey_id')->references('id')->on('surveys')->onDelete('set null');
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->foreign('survey_id')->references('id')->on('surveys')->onDelete('set null');
        });
    }


    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['guest_survey_id']);
            $table->dropForeign(['registered_user_survey_id']);
        });

        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
        });
    }
};
