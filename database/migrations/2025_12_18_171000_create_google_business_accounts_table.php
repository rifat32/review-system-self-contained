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
        Schema::create('google_business_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('account_id')->unique();
            $table->string('account_name');
            $table->string('type')->nullable(); // PERSONAL, ORGANIZATION, etc.
            $table->text('access_token'); // Will be encrypted
            $table->text('refresh_token'); // Will be encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('google_business_accounts');
    }
};
