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
        Schema::create('review_business_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('business_service_id');
            $table->unsignedBigInteger('business_area_id');
            $table->timestamps();
            
            $table->foreign('review_id')
                  ->references('id')
                  ->on('review_news')
                  ->onDelete('cascade');
                  
            $table->foreign('business_service_id')
                  ->references('id')
                  ->on('business_services')
                  ->onDelete('cascade');

            $table->foreign('business_area_id')
                  ->references('id')
                  ->on('business_areas')
                  ->onDelete('cascade');
                  
            $table->unique(['review_id', 'business_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_business_services');
    }



};
