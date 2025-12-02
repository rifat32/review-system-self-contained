<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['summary', 'detected_issue', 'opportunity', 'prediction']);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_insights');
    }


};
