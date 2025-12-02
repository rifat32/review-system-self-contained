<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->boolean('is_voice_review')->default(false)->after('staff_id');
            $table->string('voice_url')->nullable()->after('is_voice_review');
            $table->integer('voice_duration')->nullable()->after('voice_url');
            $table->json('transcription_metadata')->nullable()->after('voice_duration');
        });
    }

    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn([
                'is_voice_review',
                'voice_url',
                'voice_duration',
                'transcription_metadata',
            ]);
        });
    }
};
