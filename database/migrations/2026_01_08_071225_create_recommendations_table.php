<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();

             $table->unsignedBigInteger('business_id');
    $table->unsignedBigInteger('insight_id')->nullable();
    $table->unsignedBigInteger('rule_id')->nullable();

    $table->enum('type', ['business', 'staff', 'area']);

    $table->text('text');

    $table->string('confidence')->nullable();
    $table->integer('priority')->default(1);

    $table->json('evidence')->nullable();
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
