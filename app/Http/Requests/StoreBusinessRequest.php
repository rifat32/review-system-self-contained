<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Name' => 'required|string|max:255|unique:businesses,Name',
            'Address' => 'required|string|max:500',
            'PostCode' => 'required|string|max:20'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'Name.required' => 'Business name is required.',
            'Name.unique' => 'This business name is already taken.',
            'Address.required' => 'Business address is required.',
            'PostCode.required' => 'Post code is required.'

        ];
    }
}
