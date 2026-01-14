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
            $table->boolean('multi_tag_detection')->default(false)->after('enabled');
            $table->boolean('trigger_only_on_first_occurrence')->default(false)->after('multi_tag_detection');
            $table->enum('applies_to', ['new_reviews_only', 'all_reviews'])->default('new_reviews_only')->after('trigger_only_on_first_occurrence');
            $table->decimal('precision_rate', 5, 2)->nullable()->after('applies_to');
            $table->integer('lifetime_triggers')->default(0)->after('precision_rate');
            $table->text('ai_manager_tip')->nullable()->after('ai_when_it_triggers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_rules', function (Blueprint $table) {
            $table->dropColumn([
                'multi_tag_detection',
                'trigger_only_on_first_occurrence',
                'applies_to',
                'precision_rate',
                'lifetime_triggers',
                'ai_manager_tip'
            ]);
        });
    }
};
