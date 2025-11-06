<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewAndStuffFieldsToBusinessesTable extends Migration
{

     public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Guest user settings
            $table->boolean('is_guest_user_overall_review')->default(0)->after('id');
            $table->boolean('is_guest_user_survey')->default(0)->after('is_guest_user_overall_review');
            $table->boolean('is_guest_user_survey_required')->default(0)->after('is_guest_user_survey');
            $table->boolean('is_guest_user_show_stuffs')->default(0)->after('is_guest_user_survey_required');
            $table->boolean('is_guest_user_show_stuff_image')->default(0)->after('is_guest_user_show_stuffs');
            $table->boolean('is_guest_user_show_stuff_name')->default(0)->after('is_guest_user_show_stuff_image');

            // Registered user settings
            $table->boolean('is_registered_user_overall_review')->default(0)->after('is_guest_user_show_stuff_name');
            $table->boolean('is_registered_user_survey')->default(0)->after('is_registered_user_overall_review');
            $table->boolean('is_registered_user_survey_required')->default(0)->after('is_registered_user_survey');
            $table->boolean('is_registered_user_show_stuffs')->default(0)->after('is_registered_user_survey_required');
            $table->boolean('is_registered_user_show_stuff_image')->default(0)->after('is_registered_user_show_stuffs');
            $table->boolean('is_registered_user_show_stuff_name')->default(0)->after('is_registered_user_show_stuff_image');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                // Guest user fields
                'is_guest_user_overall_review',
                'is_guest_user_survey',
                'is_guest_user_survey_required',
                'is_guest_user_show_stuffs',
                'is_guest_user_show_stuff_image',
                'is_guest_user_show_stuff_name',

                // Registered user fields
                'is_registered_user_overall_review',
                'is_registered_user_survey',
                'is_registered_user_survey_required',
                'is_registered_user_show_stuffs',
                'is_registered_user_show_stuff_image',
                'is_registered_user_show_stuff_name',
            ]);
        });
    }





}
