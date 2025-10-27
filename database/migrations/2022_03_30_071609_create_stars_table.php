<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStarsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("value");
            $table->timestamps();
        });
        if(env("first_setup")) {
            DB::table('stars')->insert(
                array(
                    [
                        'value' => '1',
                    ],
                    [
                        'value' => '2',
                    ],
                    [
                        'value' => '3',
                    ],
                    [
                        'value' => '4',
                    ],
                    [
                        'value' => '5',
                    ],
                )
            );
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stars');
    }
}
