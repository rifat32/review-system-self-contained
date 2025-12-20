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
        Schema::table('questions', function (Blueprint $table) {
            // $table->foreignId('question_category_id')
            //     ->nullable()
            //     ->after('id')
            //     ->constrained('question_categories')
            //     ->nullOnDelete();

            $table->foreignId('question_sub_category_id')
                ->nullable()
                ->after('question_category_id')
                ->constrained('question_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['question_category_id']);
            $table->dropForeign(['question_sub_category_id']);
            $table->dropColumn([
                'question_category_id',
                'question_sub_category_id',
            ]);
        });
    }
};
