<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThresholdRatingToBusinessesTable extends Migration
{
    
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('threshold_rating', 3, 2)->default(4.00)->after('enable_location_check'); 
            // example: if average review >= threshold_rating → review gets published
        });
    }




    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('threshold_rating');
        });
    }




    

    
}
