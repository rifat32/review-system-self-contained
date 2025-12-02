<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up()
    {
        Schema::create('staff_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->decimal('rating', 3, 1);
            $table->enum('status', ['top_performing', 'needs_improvement']);
            $table->json('skill_gaps')->nullable();
            $table->json('training_recommendations')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status']);
            $table->index(['staff_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_performance_snapshots');
    }
};
