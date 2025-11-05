<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeafletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leaflets', function (Blueprint $table) {
            $table->id();
            $table->string("title")->nullable();
            $table->unsignedBigInteger("business_id")->nullable();
            $table->string("thumbnail")->nullable();
            $table->longText("leaflet_data")->nullable();
            $table->string("type")->nullable();
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
        Schema::dropIfExists('leaflets');
    }
}
