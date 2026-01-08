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
        Schema::create('insight_records', function (Blueprint $table) {
            $table->id();
            
    $table->unsignedBigInteger('business_id');
           $table->foreign('business_id')->references('id')->on('businesses');

    $table->string('main_category');
    $table->string('sub_category')->nullable();

    $table->integer('mentions_count')->default(0);
    $table->string('severity')->nullable();
    $table->string('confidence_level')->nullable();
    $table->string('trend')->nullable();

    $table->boolean('staff_blame_detected')->default(false);

    $table->json('review_ids')->nullable();

    $table->date('time_window_start')->nullable();
    $table->date('time_window_end')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insight_records');
    }
};
