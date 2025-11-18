<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SurveyUpdateRequest extends FormRequest
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
            'id' => 'required|integer|exists:surveys,id',
            'name' => 'required|string|max:255',
            'show_in_guest_user' => 'required|boolean',
            'show_in_user' => 'required|boolean',
            "survey_questions" => "array",
            "survey_questions.*" => "numeric|exists:questions,id",

        ];
    }
}
