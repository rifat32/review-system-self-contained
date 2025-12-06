<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_image')->default(1);
            $table->string('Name');
            $table->text('About')->nullable();
            $table->string('Webpage')->nullable();
            $table->string('PhoneNumber')->nullable();
            $table->string('EmailAddress')->nullable();
            $table->text('homeText')->nullable();
            $table->text('AdditionalInformation')->nullable();
            $table->text('GoogleMapApi')->nullable();
            $table->text('Address')->nullable();
            $table->string('PostCode')->nullable();
            $table->string('Logo')->nullable();
            $table->unsignedBigInteger('OwnerID');
            $table->string('Key_ID')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->enum('Status', ['approved', 'pending', 'rejected'])->default('pending');
            $table->string('Layout')->nullable();
            $table->boolean('Is_guest_user')->default(1);
            $table->boolean('is_review_slider')->default(1);
            $table->boolean('review_only')->default(0);
            $table->string('review_type')->nullable();
            $table->text('google_map_iframe')->nullable();
            $table->string('header_image')->nullable();
            $table->string('rating_page_image')->nullable();
            $table->string('placeholder_image')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('client_primary_color')->nullable();
            $table->string('client_secondary_color')->nullable();
            $table->string('client_tertiary_color')->nullable();
            $table->boolean('user_review_report')->default(1);
            $table->boolean('guest_user_review_report')->default(1);
            $table->string('pin')->nullable();
            $table->string('STRIPE_KEY')->nullable();
            $table->string('STRIPE_SECRET')->nullable();
            $table->boolean('is_report_email_enabled')->default(0);

            // Added fields from alterations
            $table->string('time_zone')->default('UTC');
            $table->boolean('is_guest_user_overall_review')->default(true);
            $table->boolean('is_guest_user_survey')->default(false);
            $table->boolean('is_guest_user_survey_required')->default(false);
            $table->boolean('is_guest_user_show_stuffs')->default(false);
            $table->boolean('is_guest_user_show_stuff_image')->default(false);
            $table->boolean('is_guest_user_show_stuff_name')->default(false);
            $table->boolean('is_registered_user_overall_review')->default(true);
            $table->boolean('is_registered_user_survey')->default(false);
            $table->boolean('is_registered_user_survey_required')->default(false);
            $table->boolean('is_registered_user_show_stuffs')->default(false);
            $table->boolean('is_registered_user_show_stuff_image')->default(false);
            $table->boolean('is_registered_user_show_stuff_name')->default(false);
            $table->boolean('enable_ip_check')->default(false);
            $table->boolean('enable_location_check')->default(false);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('review_distance_limit')->default(100);
            $table->decimal('threshold_rating', 3, 1)->default(3.0);
            $table->json('review_labels')->nullable();
            $table->unsignedBigInteger('guest_survey_id')->nullable();
            $table->unsignedBigInteger('registered_user_survey_id')->nullable();
            $table->boolean('enable_detailed_survey')->default(false);
            $table->json('export_settings')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('businesses');
    }
};
