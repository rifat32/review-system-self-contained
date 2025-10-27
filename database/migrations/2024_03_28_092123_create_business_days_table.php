<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('business_days', function (Blueprint $table) {
            $table->id();
            $table->integer('day'); // E.g., 1 for Monday, 2 for Tuesday, etc.
            $table->unsignedBigInteger('business_id');
            $table->boolean('is_weekend');
            $table->timestamps();
               // Add foreign key constraint
    $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('business_days');
    }
}
