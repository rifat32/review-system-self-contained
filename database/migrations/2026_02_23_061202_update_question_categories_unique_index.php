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
        // Check if the unique index exists before dropping
        $indexExists = DB::select("SHOW INDEX FROM question_categories WHERE Key_name = 'question_categories_title_unique'");

        if (!empty($indexExists)) {
            Schema::table('question_categories', function (Blueprint $table) {
                $table->dropUnique(['title']);
            });
        }

        // Check if the composite unique index already exists
        $compositeIndexExists = DB::select("SHOW INDEX FROM question_categories WHERE Key_name = 'question_categories_title_business_id_unique'");

        if (empty($compositeIndexExists)) {
            Schema::table('question_categories', function (Blueprint $table) {
                $table->unique(['title', 'business_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_categories', function (Blueprint $table) {
            $table->dropUnique(['title', 'business_id']);
            $table->unique('title');
        });
    }
};
