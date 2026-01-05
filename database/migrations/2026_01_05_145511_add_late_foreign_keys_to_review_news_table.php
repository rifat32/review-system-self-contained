<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {

            $table->foreign('survey_id')
                ->references('id')
                ->on('surveys')
                ->nullOnDelete();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->nullOnDelete();
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
