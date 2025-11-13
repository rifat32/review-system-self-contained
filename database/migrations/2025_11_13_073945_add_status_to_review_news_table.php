<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToReviewNewsTable extends Migration
{

    
    public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('emotion'); // pending, published, rejected
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }







}
