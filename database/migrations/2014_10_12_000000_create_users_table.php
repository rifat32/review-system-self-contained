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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_Name')->nullable();
            $table->string('last_Name')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('resetPasswordToken')->nullable();
            $table->timestamp('resetPasswordExpires')->nullable();
            $table->string('type')->nullable();
            $table->string('pin')->nullable();
            $table->string('post_code')->nullable();
            $table->string('Address')->nullable();
            $table->string('door_no')->nullable();
            $table->string('email')->unique();
            $table->string('email_verify_token')->nullable();
            $table->timestamp('email_verify_token_expires')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('login_attempts')->default(0);
            $table->timestamp('last_failed_login_attempt_at')->nullable();
            $table->rememberToken();

            // Added fields from alterations
            $table->unsignedBigInteger('business_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('job_title')->nullable();
            $table->date('join_date')->nullable();
            $table->json('skills')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
