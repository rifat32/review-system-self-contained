<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveys', function (Blueprint $table) {
            
            $table->id();

            $table->string('name');

            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses');

            $table->boolean('show_in_guest_user');
            $table->boolean('show_in_user');

          

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
        Schema::dropIfExists('surveys');
    }
}
