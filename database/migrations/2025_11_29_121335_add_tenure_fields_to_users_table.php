<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('join_date')->nullable()->after('position');
            $table->json('skills')->nullable()->after('join_date');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['join_date', 'skills']);
        });
    }
    
};
