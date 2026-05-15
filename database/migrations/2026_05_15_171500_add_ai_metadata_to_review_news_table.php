<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('review_news', function (Blueprint $table) {
            if (!Schema::hasColumn('review_news', 'ai_processed_at')) {
                $table->timestamp('ai_processed_at')->nullable()->after('is_ai_processed');
            }
            if (!Schema::hasColumn('review_news', 'ai_model')) {
                $table->string('ai_model', 50)->nullable()->after('ai_processed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn(['ai_processed_at', 'ai_model']);
        });
    }
};
