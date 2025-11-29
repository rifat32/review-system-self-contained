<?php

namespace App\Http\Requests;

use App\Rules\ValidBusiness;
use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
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
        $rules = [
            'business_id' => ['required', 'integer', new ValidBusiness()],
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ];

        // if ($this->isMethod('patch') || $this->isMethod('put')) {
        //     $rules['id'] = 'required|integer';
        // }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'The branch ID is required.',
            'id.integer' => 'The branch ID must be an integer.',
            'business_id.required' => 'The business ID is required.',
            'name.required' => 'The branch name is required.',
            'name.string' => 'The branch name must be a string.',
            'name.max' => 'The branch name may not be greater than 255 characters.',
            'address.string' => 'The address must be a string.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 20 characters.',
            'email.email' => 'The email must be a valid email address.',
            'email.max' => 'The email may not be greater than 255 characters.',
        ];
    }
}
