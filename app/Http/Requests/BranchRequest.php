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
            'is_geo_enabled' => 'required|boolean',
            'lat' => 'nullable|string',
            'long' => 'nullable|string',
        ];

        if ($this->isMethod('post')) {
            $rules['branch_code'] = 'required|string|max:50|unique:branches,branch_code';
        }

        if ($this->isMethod('patch') || $this->isMethod('put')) {
            $rules['branch_code'] = 'nullable|string|max:50|unique:branches,branch_code,' . ($this->route('id'));
        }

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
            'is_geo_enabled.boolean' => 'The geo enabled status must be true or false.',
            'branch_code.string' => 'The branch code must be a string.',
            'branch_code.max' => 'The branch code may not be greater than 50 characters.',
            'branch_code.unique' => 'The branch code has already been taken.',
            'lat.string' => 'The latitude must be a string.',
            'long.string' => 'The longitude must be a string.',
        ];
    }
}
