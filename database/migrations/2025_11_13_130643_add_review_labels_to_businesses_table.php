<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewLabelsToBusinessesTable extends Migration
{
   public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('review_labels')->nullable()->after('threshold_rating');
  
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('review_labels');
        });
    }

}
