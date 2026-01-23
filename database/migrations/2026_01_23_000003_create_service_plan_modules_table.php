<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_plan_modules', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('service_plan_id')->constrained('service_plans')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->unsignedBigInteger("created_by")->nullable();
            $table->timestamps();

            $table->unique(['service_plan_id', 'module_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_plan_modules');
    }
};
