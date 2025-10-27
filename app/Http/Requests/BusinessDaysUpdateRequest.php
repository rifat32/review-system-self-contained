<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessDaysUpdateRequest extends FormRequest
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
            'times' => 'required|array',
            'times.*.day' => 'required|numeric',
            'times.*.time_slots' => 'required|array',
          'times.*.time_slots.*.start_at' => 'required|date_format:H:i:s',
           'times.*.time_slots.*.end_at' => 'required|date_format:H:i:s',

            'times.*.is_weekend' => 'required|boolean',
       ];
    }
}
