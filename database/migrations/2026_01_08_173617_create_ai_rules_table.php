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
    Schema::create('ai_rules', function (Blueprint $table) {
        $table->id();
        $table->string('rule_id', 100)->unique();
        $table->string('rule_name', 255);
        $table->text('description')->nullable();
        
        $table->enum('scope', ['system', 'business_type', 'business']);
        $table->string('business_type', 50)->nullable();
        $table->unsignedBigInteger('business_id')->nullable();
        
        $table->string('category', 50);
        $table->string('priority', 20)->default('medium');
        $table->boolean('enabled')->default(true);
        
        $table->json('conditions');
        $table->json('actions');
        $table->json('explainability')->nullable();
        
        $table->string('created_by', 50)->default('system');
        $table->integer('version')->default(1);

         $table->foreign('business_id')->references('id')->on('businesses');
        
        $table->timestamps();
        
        $table->index(['scope', 'business_type', 'business_id']);
        $table->index(['category', 'enabled']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_rules');
    }
};
