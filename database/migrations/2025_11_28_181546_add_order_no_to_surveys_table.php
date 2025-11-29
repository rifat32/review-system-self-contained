<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    


    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->integer('order_no')->default(0)->after('id');
        });
    }


    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('order_no');
        });
    }
   




};
