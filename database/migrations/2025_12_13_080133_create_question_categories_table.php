<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('parent_question_category_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('parent_question_category_id')->references('id')->on('question_categories')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('is_active');
            $table->index('is_default');
            $table->index('business_id');
            $table->index('parent_question_category_id');
        });

        // Insert default "staff" category
        DB::table('question_categories')->insert([
            'title' => 'Staff',
            'description' => 'Default category for staff-related questions',
            'is_active' => true,
            'is_default' => true,
            'business_id' => null,
            'parent_question_category_id' => null,
            'created_by' => null, // Nullable field
            'created_at' => now(),
            'updated_at' => now(),
        ]);

       
    }

  
};
