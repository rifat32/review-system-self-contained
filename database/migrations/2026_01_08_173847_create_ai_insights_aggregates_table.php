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
    Schema::create('ai_insights_aggregate', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('business_id');
        
        $table->string('insight_type', 50);
        $table->string('key_name', 100);
        $table->integer('count')->default(0);
        $table->string('severity', 20)->nullable();
        
        $table->timestamp('first_seen')->nullable();
        $table->timestamp('last_seen')->nullable();
        
        $table->json('metadata')->nullable();
        
        $table->timestamps();
        
        $table->index(['business_id', 'insight_type']);
        $table->index(['key_name', 'count']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_insights_aggregates');
    }
};
