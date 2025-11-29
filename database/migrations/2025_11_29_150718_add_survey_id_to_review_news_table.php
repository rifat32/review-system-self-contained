<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->foreignId('survey_id')->nullable()->after('business_id')->constrained()->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['survey_id']);
            $table->dropColumn('survey_id');
        });
    }
};
