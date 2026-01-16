<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewValueNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('review_value_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("question_id");
            $table->unsignedBigInteger("star_id");

            $table->unsignedBigInteger("review_id");

            $table->foreign('question_id')->references('id')->on('questions')->onDelete('restrict');
            $table->foreign('star_id')->references('id')->on('stars')->onDelete('restrict');

            $table->foreign('review_id')->references('id')->on('review_news')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('review_value_news');
    }
}
