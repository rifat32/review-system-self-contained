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
        Schema::create('google_business_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_business_location_id')
                ->constrained('google_business_locations')
                ->onDelete('cascade');
            $table->string('review_id')->unique();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_photo_url')->nullable();
            $table->enum('star_rating', ['ONE', 'TWO', 'THREE', 'FOUR', 'FIVE']);
            $table->text('comment')->nullable();
            $table->text('review_reply')->nullable();
            $table->timestamp('review_reply_updated_at')->nullable();
            $table->timestamp('review_created_at')->nullable();
            $table->timestamp('review_updated_at')->nullable();
            $table->timestamps();

            $table->index('google_business_location_id');
            $table->index('star_rating');
            $table->index('review_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('google_business_reviews');
    }
};
