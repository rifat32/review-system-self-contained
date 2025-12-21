<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SurveyCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'show_in_guest_user' => 'required|boolean',
            'show_in_user' => 'required|boolean',

            "survey_questions" => "array",
            "survey_questions.*" => "numeric|exists:questions,id",

              'business_service_ids' => 'array', // Add this
        'business_service_ids.*' => 'integer|exists:business_services,id', // Add this

        ];
    }
}
