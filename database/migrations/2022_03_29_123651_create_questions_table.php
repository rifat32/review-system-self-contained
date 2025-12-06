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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->enum('type', ['star', 'emoji', 'numbers', 'heart'])->default('star');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Added fields from alterations
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->boolean('is_overall')->default(false);
            $table->boolean('show_in_guest_user')->default(true);
            $table->boolean('show_in_user')->default(true);
            $table->string('survey_name')->nullable();
            $table->integer('order_no')->default(0);
            $table->boolean('is_staff')->default(false);

            $table->timestamps();
        });

        // Insert default questions
        DB::table('questions')->insert([
            [
                'question' => 'How was your overall experience?',
                'type' => 'star',
                'is_default' => true,
                'is_active' => true,
                'is_overall' => true,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'How would you rate the quality of service?',
                'type' => 'star',
                'is_default' => true,
                'is_active' => true,
                'is_overall' => false,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'Would you recommend us to others?',
                'type' => 'star',
                'is_default' => true,
                'is_active' => true,
                'is_overall' => false,
                'show_in_guest_user' => true,
                'show_in_user' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questions');
    }
};
