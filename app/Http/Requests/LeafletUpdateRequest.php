<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeafletUpdateRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'business_id' => 'required|integer|exists:businesses,id',
            'thumbnail' => 'nullable|string|max:255',
            'leaflet_data' => 'nullable|json',
            'type' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'business_id.required' => 'The business ID is required.',
            'business_id.exists' => 'The selected business does not exist.',
            'leaflet_data.json' => 'The leaflet data must be valid JSON.',
        ];
    }
}
