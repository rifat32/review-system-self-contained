<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessAreaRequest extends FormRequest
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
        $rules = [
            'business_service_id' => 'required|integer|exists:business_services,id',
            'area_name' => 'required|string|max:255',
        ];

        // If updating, add ID validation
        // if ($this->isMethod('put') || $this->isMethod('patch')) {
        //     $rules['id'] = 'required|integer|exists:business_areas,id';
        // }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'business_service_id.required' => 'Business service ID is required.',
            'business_service_id.exists' => 'Selected business service does not exist.',
            'area_name.required' => 'Area name is required.',
            'area_name.max' => 'Area name cannot exceed 255 characters.',
            'id.required' => 'Area ID is required for updates.',
            'id.exists' => 'Selected area does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'business_service_id' => 'business service',
            'area_name' => 'area name',
            'is_active' => 'is active',
        ];
    }
}
