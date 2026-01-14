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
        Schema::table('ai_rules', function (Blueprint $table) {
            $table->string('ai_explanation_title')->nullable()->after('explainability');
            $table->text('ai_plain_explanation')->nullable()->after('ai_explanation_title');
            $table->text('ai_why_it_matters')->nullable()->after('ai_plain_explanation');
            $table->text('ai_when_it_triggers')->nullable()->after('ai_why_it_matters');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_when_it_triggers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_rules', function (Blueprint $table) {
            $table->dropColumn([
                'ai_explanation_title',
                'ai_plain_explanation',
                'ai_why_it_matters',
                'ai_when_it_triggers',
                'ai_generated_at'
            ]);
        });
    }
};
