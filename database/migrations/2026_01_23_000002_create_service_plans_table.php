<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->double('price');
            $table->double('duration_months');
            $table->unsignedBigInteger('openai_token_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->double('set_up_amount')->default(0);
            $table->unsignedBigInteger("created_by")->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_plans');
    }
};
