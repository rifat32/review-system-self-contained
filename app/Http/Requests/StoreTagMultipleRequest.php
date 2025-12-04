<?php

namespace App\Http\Requests;

use App\Rules\ValidBusiness;
use App\Rules\ValidBusinessOwner;
use Illuminate\Foundation\Http\FormRequest;

class StoreTagMultipleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->route('businessId');

        return [
            'tags' => [
                'required',
                'array',
                'min:1'
            ],
            'tags.*' => [
                'required',
                'string',
                'max:255'
            ],
            'businessId' => [
                'required',
                'integer',
                new ValidBusinessOwner($businessId, true) // Check ownership
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'tags.required' => 'Tags array is required',
            'tags.array' => 'Tags must be an array',
            'tags.min' => 'At least one tag is required',
            'tags.*.required' => 'Each tag must have a value',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag cannot exceed 255 characters'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'businessId' => $this->route('businessId')
        ]);
    }
}
