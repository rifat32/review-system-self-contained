<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaymentTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->text("description");
            $table->boolean("is_active")->default(false);
            $table->unsignedBigInteger('business_id');
            $table->timestamps();
        });


        if (env("first_setup")) {
            DB::table("payment_types")->insert([
                "name" => "cash",
                "description" => "cash",
                "is_active" => true
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_types');
    }
}
