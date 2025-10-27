<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    /*
    Owner_id = id


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
            $table->string('resetPasswordExpires')->nullable();
            $table->string('type')->nullable();
            $table->string('pin')->nullable();
            $table->string('post_code')->nullable();
            $table->string('Address')->nullable();
            $table->string('door_no')->nullable();

            $table->string('email')->unique();
            $table->string('email_verify_token')->nullable();
            $table->string('email_verify_token_expires')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            $table->integer('login_attempts')->default(0);
            $table->dateTime('last_failed_login_attempt_at')->nullable();

            $table->rememberToken();
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
}
