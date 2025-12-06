<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->boolean('is_default')->default(false);

            // Added fields from alterations
            $table->boolean('is_active')->default(true);
            $table->string('category')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();

            $table->timestamps();
        });

        // Insert default tags
        $defaultTags = [
            ['tag' => 'Excellent Service', 'is_default' => true, 'is_active' => true, 'category' => 'service', 'sentiment' => 'positive'],
            ['tag' => 'Friendly Staff', 'is_default' => true, 'is_active' => true, 'category' => 'staff', 'sentiment' => 'positive'],
            ['tag' => 'Clean Environment', 'is_default' => true, 'is_active' => true, 'category' => 'environment', 'sentiment' => 'positive'],
            ['tag' => 'Quick Service', 'is_default' => true, 'is_active' => true, 'category' => 'service', 'sentiment' => 'positive'],
            ['tag' => 'Great Value', 'is_default' => true, 'is_active' => true, 'category' => 'value', 'sentiment' => 'positive'],
            ['tag' => 'Professional', 'is_default' => true, 'is_active' => true, 'category' => 'staff', 'sentiment' => 'positive'],
            ['tag' => 'Convenient Location', 'is_default' => true, 'is_active' => true, 'category' => 'location', 'sentiment' => 'positive'],
            ['tag' => 'High Quality', 'is_default' => true, 'is_active' => true, 'category' => 'quality', 'sentiment' => 'positive'],
            ['tag' => 'Comfortable', 'is_default' => true, 'is_active' => true, 'category' => 'environment', 'sentiment' => 'positive'],
            ['tag' => 'Recommend', 'is_default' => true, 'is_active' => true, 'category' => 'general', 'sentiment' => 'positive'],
            ['tag' => 'Poor Service', 'is_default' => true, 'is_active' => true, 'category' => 'service', 'sentiment' => 'negative'],
            ['tag' => 'Rude Staff', 'is_default' => true, 'is_active' => true, 'category' => 'staff', 'sentiment' => 'negative'],
            ['tag' => 'Dirty', 'is_default' => true, 'is_active' => true, 'category' => 'environment', 'sentiment' => 'negative'],
            ['tag' => 'Slow Service', 'is_default' => true, 'is_active' => true, 'category' => 'service', 'sentiment' => 'negative'],
            ['tag' => 'Overpriced', 'is_default' => true, 'is_active' => true, 'category' => 'value', 'sentiment' => 'negative'],
            ['tag' => 'Unprofessional', 'is_default' => true, 'is_active' => true, 'category' => 'staff', 'sentiment' => 'negative'],
            ['tag' => 'Poor Location', 'is_default' => true, 'is_active' => true, 'category' => 'location', 'sentiment' => 'negative'],
            ['tag' => 'Low Quality', 'is_default' => true, 'is_active' => true, 'category' => 'quality', 'sentiment' => 'negative'],
            ['tag' => 'Uncomfortable', 'is_default' => true, 'is_active' => true, 'category' => 'environment', 'sentiment' => 'negative'],
            ['tag' => 'Not Recommend', 'is_default' => true, 'is_active' => true, 'category' => 'general', 'sentiment' => 'negative'],
            ['tag' => 'Average', 'is_default' => true, 'is_active' => true, 'category' => 'general', 'sentiment' => 'neutral'],
            ['tag' => 'Okay', 'is_default' => true, 'is_active' => true, 'category' => 'general', 'sentiment' => 'neutral'],
        ];

        foreach ($defaultTags as $tag) {
            $tag['created_at'] = now();
            $tag['updated_at'] = now();
            DB::table('tags')->insert($tag);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tags');
    }
};
