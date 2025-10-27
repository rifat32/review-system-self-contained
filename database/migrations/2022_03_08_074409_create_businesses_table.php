<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessesTable extends Migration
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


            $table->boolean("show_image")->nullable()->default(1);
     


            $table->string("Name");
            $table->text("About")->nullable();
            $table->string("Webpage")->nullable();
            $table->string("PhoneNumber")->nullable();
            $table->string("EmailAddress")->nullable();
            $table->text("homeText")->nullable();
            $table->string("AdditionalInformation")->nullable();
            $table->string("GoogleMapApi")->nullable();

            $table->string("Address");
            $table->string("PostCode");
            $table->string("Logo")->nullable();
            $table->unsignedBigInteger("OwnerID");
            $table->string("Key_ID")->nullable();
            $table->date("expiry_date")->nullable();

            $table->string("Status")->nullable();
            $table->string("Layout")->nullable();

            $table->boolean("enable_question");



    


            $table->boolean("Is_guest_user")->default(false);
            $table->boolean("is_review_silder")->default(false);
            $table->boolean("review_only")->default(true)->nullable(false);


            $table->string("review_type")->default("star")->nullable(false);

            $table->text("google_map_iframe")->nullable();



      


        

            $table->string("header_image")->default("/header_image/default.webp");
            $table->string("rating_page_image")->default("/rating_page_image/default.webp");
            $table->string("placeholder_image")->default("/placeholder_image/default.webp");







       



            $table->string("primary_color")->nullable(true);
            $table->string("secondary_color")->nullable(true);



            $table->string("client_primary_color")->nullable(true)->default("#172c41");
            $table->string("client_secondary_color")->nullable(true)->default("#ac8538");
            $table->string("client_tertiary_color")->nullable(true)->default("#fffffff");

            $table->boolean("user_review_report")->default(0);
            $table->boolean("guest_user_review_report")->default(0);



            $table->string("pin")->nullable(true);



    






            $table->string("STRIPE_KEY")->nullable(true);
            $table->string("STRIPE_SECRET")->nullable(true);




          


           $table->boolean("is_report_email_enabled")->nullable(true)->default(0);


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
}
