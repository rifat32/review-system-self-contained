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
        Schema::table('review_news', function (Blueprint $table) {
            $table->unsignedBigInteger('business_area_id')->nullable()->after('branch_id');
            $table->decimal('ai_confidence', 3, 2)->nullable()->after('is_ai_processed')->comment('Confidence score 0.00-1.00');
            $table->string('sentiment_label', 20)->nullable()->after('sentiment_score')->comment('very_negative, negative, neutral, positive, very_positive');
            $table->json('openai_raw_response')->nullable()->after('staff_suggestions');
            $table->boolean('is_abusive')->default(false)->after('language');
            $table->text('summary')->nullable()->after('is_abusive');
            
            // Add foreign key
            $table->foreign('business_area_id')->references('id')->on('business_areas')->onDelete('set null');
            
            // Add indexes for performance
            $table->index('sentiment_label');
            $table->index('business_area_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropForeign(['business_area_id']);
            $table->dropIndex(['sentiment_label']);
            $table->dropIndex(['business_area_id']);
            
            $table->dropColumn([
                'business_area_id',
                'ai_confidence',
                'sentiment_label',
                'openai_raw_response',
                'is_abusive',
                'summary'
            ]);
        });
    }
};
