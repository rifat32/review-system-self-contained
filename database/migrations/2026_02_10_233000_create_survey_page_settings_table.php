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
        Schema::create('survey_page_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            
            $table->string('background_color')->default('#0A4B67');
            $table->string('overall_heading')->default('Your Overall Experience');
            $table->string('survey_heading')->default('How would you rate us?');
            $table->string('heading_color')->default('#0A4B67');
            $table->string('sub_heading')->default('Please help us improve our services by sharing your experience');
            $table->string('sub_heading_color')->default('#E8EEFF');
            $table->string('question_text_color')->default('#FFFFFF');
            $table->string('question_background_color')->default('#FFFFFF');
            $table->string('tag_text_color')->default('#FFFFFF');
            $table->string('tag_background_color')->default('#32CD32');
            $table->string('tag_active_text_color')->default('#32CD32');
            $table->string('tag_active_background_color')->default('#FFFFFF');
            $table->string('service_text_color')->default('#FFFFFF');
            $table->string('service_background_color')->default('#FFFFFF');
            $table->string('service_area_text_color')->default('#0A4B67');
            $table->string('service_area_background_color')->default('#FFFFFF');
            $table->string('active_service_area_text_color')->default('#FFFFFF');
            $table->string('active_service_area_background_color')->default('#32CD32');
            $table->string('staff_heading')->default('Who served you today?');
            $table->string('staff_heading_color')->default('#FFFFFF');
            $table->string('staff_background_color')->default('#FFFFFF');
            $table->string('staff_card_background_color')->default('#F1F1F1');
            $table->string('staff_name_color')->default('#0A4B67');
            $table->string('staff_role_color')->default('#32CD32');
            $table->string('staff_active_background_color')->default('#BBF7D0');
            $table->string('staff_active_border_color')->default('#32CD32');
            $table->string('remarks_button_text')->default('Anything else to add?');
            $table->string('remarks_button_text_color')->default('#32CD32');
            $table->string('remarks_button_background_color')->default('#FFFFFF');
            $table->string('remarks_text')->default('Remarks (Optional)');
            $table->string('remarks_text_color')->default('#FFFFFF');
            $table->string('remarks_background_color')->default('#FFFFFF');
            $table->string('field_background_color')->default('#EFEFEF');
            $table->string('field_text_color')->default('#0A4B67');
            $table->string('details_heading')->default('Provide your details');
            $table->string('details_heading_color')->default('#FFFFFF');
            $table->string('details_background_color')->default('#FFFFFF');
            $table->string('details_label_color')->default('#000000');
            $table->string('actions_background_color')->default('#FFFFFF');
            $table->string('actions_buttons_text_color')->default('#FFFFFF');
            $table->string('actions_button_background_color')->default('#0A4B67');
            
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
        Schema::dropIfExists('survey_page_settings');
    }
};
