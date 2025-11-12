<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewRulesToBusinessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('enable_ip_check')->default(true);
            $table->boolean('enable_location_check')->default(true);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('review_distance_limit')->default(500); // meters
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            // check existence before dropping to avoid errors on some DB drivers
            if (Schema::hasColumn('businesses', 'enable_ip_check')) {
                $table->dropColumn('enable_ip_check');
            }
            if (Schema::hasColumn('businesses', 'enable_location_check')) {
                $table->dropColumn('enable_location_check');
            }
            if (Schema::hasColumn('businesses', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('businesses', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('businesses', 'review_distance_limit')) {
                $table->dropColumn('review_distance_limit');
            }
        });
    }
}
