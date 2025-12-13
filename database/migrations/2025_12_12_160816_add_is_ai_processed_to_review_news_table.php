<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {
            // Add is_ai_processed field with default false
            $table->boolean('is_ai_processed')->default(0)->after('is_voice_review');
            
        });
    }

    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn('is_ai_processed');
        });
    }




















};
