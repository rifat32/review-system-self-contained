<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openai_token_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->unsignedBigInteger('review_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('model')->index();
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openai_token_usage');
    }
};
