<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("receiver_id")->nullable();
            $table->unsignedBigInteger("sender_id")->nullable();
            $table->unsignedBigInteger("business_id")->nullable();
            $table->string("sender_type")->nullable();
            $table->string("message")->nullable();
            $table->string("title")->nullable();
            
            $table->string('type')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('link')->nullable();
            $table->enum('priority', ['low', 'normal', 'high',])->default('normal');

            $table->enum("status", ['read', 'unread'])->default("unread")->nullable();

            $table->unsignedBigInteger("entity_id");
            $table->json("entity_ids")->nullable();
            // 
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');




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
        Schema::dropIfExists('notifications');
    }
}
