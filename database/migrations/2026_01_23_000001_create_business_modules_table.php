<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_modules', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->unsignedBigInteger("created_by")->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'module_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_modules');
    }
};
