<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('review_news', function (Blueprint $table) {
            $table->id();
            $table->string("description")->nullable();
            $table->unsignedBigInteger("business_id")->nullable();
            $table->string("rate")->nullable();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->unsignedBigInteger("guest_id")->nullable();

            $table->string("comment")->nullable();
            // $table->unsignedBigInteger("question_id");
            // $table->unsignedBigInteger("star_id");
            // $table->unsignedBigInteger("tag_id");


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
        Schema::dropIfExists('review_news');
    }
}
