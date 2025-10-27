<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessTimeSlotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('business_time_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_day_id'); // Foreign key to the business_days table
            $table->time('start_at');
            $table->time('end_at');
            $table->timestamps();

            $table->foreign('business_day_id')->references('id')->on('business_days')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('business_time_slots');
    }
}
