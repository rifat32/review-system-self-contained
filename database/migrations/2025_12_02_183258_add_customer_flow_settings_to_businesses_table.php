<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('enable_detailed_survey')->default(false)->after('review_distance_limit');

            $table->json('export_settings')->nullable()->after('enable_detailed_survey');
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'enable_detailed_survey',
                'export_settings',
            ]);
        });
    }
};
