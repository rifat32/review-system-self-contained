<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
  public function up(): void
{
    Schema::table('tags', function (Blueprint $table) {
        $table->string('category')->nullable();
        $table->enum('sentiment', ['positive','neutral','negative'])->nullable();
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
          $table->dropColumn(['category', 'sentiment']);

        });
    }
}
