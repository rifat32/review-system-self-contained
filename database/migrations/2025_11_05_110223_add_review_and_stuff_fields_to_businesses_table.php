<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewAndStuffFieldsToBusinessesTable extends Migration
{

      public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('overall_review')->default(0)->after('id');
            $table->boolean('survey')->default(0)->after('overall_review');
            $table->boolean('show_stuffs')->default(0)->after('survey');
            $table->boolean('show_stuff_image')->default(0)->after('show_stuffs');
            $table->boolean('show_stuff_name')->default(0)->after('show_stuff_image');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'overall_review',
                'survey',
                'show_stuffs',
                'show_stuff_image',
                'show_stuff_name',
            ]);
        });
    }





}
