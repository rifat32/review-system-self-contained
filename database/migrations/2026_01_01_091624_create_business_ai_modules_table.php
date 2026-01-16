<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_ai_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('total_tokens_used')->default(0);
            $table->decimal('total_cost_usd', 12, 6)->default(0);

            // Required modules (always true)
            $table->boolean('language_translation')->default(true);
            $table->boolean('sentiment_analysis')->default(true);
            $table->boolean('emotion_detection')->default(true);
            $table->boolean('abuse_detection')->default(true);
            $table->boolean('explainability')->default(true);

            // Optional modules (can be disabled)
            $table->boolean('category_analysis')->default(true);
            $table->boolean('staff_intelligence')->default(true);
            $table->boolean('service_unit_intelligence')->default(true);
            $table->boolean('business_recommendations')->default(true);
            $table->boolean('alerts')->default(true);

            $table->timestamps();
            $table->unique('business_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_ai_modules');
    }
};
