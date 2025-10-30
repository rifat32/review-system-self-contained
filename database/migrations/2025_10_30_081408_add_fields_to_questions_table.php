<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
{
    Schema::table('questions', function (Blueprint $table) {

        $table->float('weight')->default(1.0);
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
        Schema::table('questions', function (Blueprint $table) {
          $table->dropColumn([ 'weight', 'sentiment']);

        });
    }
}
