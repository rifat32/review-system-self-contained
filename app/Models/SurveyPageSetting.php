<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyPageSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'background_color',
        'overall_heading',
        'survey_heading',
        'heading_color',
        'sub_heading',
        'sub_heading_color',
        'question_text_color',
        'question_background_color',
        'tag_text_color',
        'tag_background_color',
        'tag_active_text_color',
        'tag_active_background_color',
        'service_text_color',
        'service_background_color',
        'service_area_text_color',
        'service_area_background_color',
        'active_service_area_text_color',
        'active_service_area_background_color',
        'staff_heading',
        'staff_heading_color',
        'staff_background_color',
        'staff_card_background_color',
        'staff_name_color',
        'staff_role_color',
        'staff_active_background_color',
        'staff_active_border_color',
        'remarks_button_text',
        'remarks_button_text_color',
        'remarks_button_background_color',
        'remarks_text',
        'remarks_text_color',
        'remarks_background_color',
        'field_background_color',
        'field_text_color',
        'details_heading',
        'details_heading_color',
        'details_background_color',
        'details_label_color',
        'actions_background_color',
        'actions_buttons_text_color',
        'actions_button_background_color',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
