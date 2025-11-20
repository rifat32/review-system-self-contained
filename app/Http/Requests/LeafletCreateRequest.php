<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeafletCreateRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'business_id' => 'required|integer|exists:businesses,id',
            'thumbnail' => 'nullable|string|max:255',
            'leaflet_data' => 'required|json',
            'type' => 'required|string|max:255',
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
            'title.required' => 'The title field is required.',
            'business_id.required' => 'The business ID is required.',
            'business_id.exists' => 'The selected business does not exist.',
            'leaflet_data.required' => 'The leaflet data is required.',
            'leaflet_data.json' => 'The leaflet data must be valid JSON.',
            'type.required' => 'The type field is required.',
        ];
    }
}
