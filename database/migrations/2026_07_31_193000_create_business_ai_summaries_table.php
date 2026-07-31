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
        Schema::create('business_ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->integer('version')->default(1);
            $table->text('summary');
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('top_topics')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('trend')->nullable();
            $table->float('confidence')->nullable();
            $table->integer('total_reviews')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_ai_summaries');
    }
};
