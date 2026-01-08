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
        Schema::create('recommendation_rules', function (Blueprint $table) {
            $table->id();

             $table->enum('applies_to', ['business', 'staff', 'area']);

    $table->string('main_category')->nullable();
    $table->string('sub_category')->nullable();

    $table->json('condition_json');

    $table->text('recommendation_template');

    $table->integer('priority')->default(1);

    $table->string('confidence_required')->nullable();

    
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_rules');
    }
};
