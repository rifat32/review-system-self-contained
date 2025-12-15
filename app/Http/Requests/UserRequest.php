<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\UniqueEmail;

class UserRequest extends FormRequest
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
            'first_Name' => 'required|string|max:255',
            'last_Name' => 'nullable|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', new UniqueEmail($this->route('id'))],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|in:branch_manager,business_staff',
            'post_code' => 'nullable|string|max:10',
            'Address' => 'nullable|string|max:255',
            'door_no' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'image' => 'nullable|string',
            'job_title' => 'nullable|string|max:255',
            'join_date' => 'nullable|date',
            'skills' => 'nullable|string',
        ];

        if ($this->isMethod('post')) {
            $rules['password'] = 'required|string|min:6';
        }

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
            'first_Name.required' => 'First name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'role.required' => 'User role is required.',
            'role.in' => 'Invalid user role selected.',
        ];
    }
}
