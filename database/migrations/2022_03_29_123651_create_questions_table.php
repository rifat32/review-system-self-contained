<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string("question");
            $table->enum('type', [
             'star',
             'emoji',
             'numbers',
             'heart'
            ])->default("star")->nullable();
            $table->unsignedBigInteger("business_id")->nullable();
            $table->boolean("is_default")->default(false);
            $table->boolean("is_active")->default(false);

            $table->timestamps();
        });

        if(env("first_setup")) {

        DB::table('questions')
        ->insert(    array(
           [
            "question" => "What was your overall experience like with our product/service?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ],
           [
            "question" => "How would you rate our customer service and support?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ],
           [
            "question" => "How likely are you to recommend our product/service to others?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ],
           [
            "question" => "Were the prices for the products or services offered reasonable?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ],
           [
            "question" => "How was the customer service you received?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ],
           [
            "question" => "Were the employees friendly and helpful?",
            "type"=> "star",
            "is_default" => 1,
            "is_active" => 1

           ]


        ));

    }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questions');
    }
}
