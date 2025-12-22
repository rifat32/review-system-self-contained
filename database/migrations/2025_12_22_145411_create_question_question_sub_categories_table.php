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
        Schema::create('q_q_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained("questions")->onDelete('cascade');
            $table->foreignId('question_sub_category_id')->constrained("question_categories")->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('q_q_sub_categories');
    }
};
