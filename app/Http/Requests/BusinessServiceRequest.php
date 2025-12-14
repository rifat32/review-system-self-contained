<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessServiceRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'question_title' => 'nullable|string|max:255',
        ];

        // If updating, add ID validation
        // if ($this->isMethod('put') || $this->isMethod('patch')) {
        //     $rules['id'] = 'required|integer|exists:business_services,id';
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
            'name.required' => 'Service name is required.',
            'name.max' => 'Service name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'question_title.max' => 'Question title cannot exceed 255 characters.',
            'id.required' => 'Service ID is required for updates.',
            'id.exists' => 'Selected service does not exist.',
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
            'question_title' => 'question title',
        ];
    }
}
