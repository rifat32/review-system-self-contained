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
        Schema::connection('logs')->create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('api_url')->nullable();
            $table->string('token')->nullable();
            $table->string('user')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity')->nullable();
            $table->longText('payload')->nullable();
            $table->text('queries')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('request_method')->nullable();
            $table->string('device')->nullable();
            $table->boolean('is_error')->default(false);
            $table->text('message')->nullable();
            $table->longText('error_trace')->nullable();
            $table->integer('status_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('logs')->dropIfExists('activity_logs');
    }
};
