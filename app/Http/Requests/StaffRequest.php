<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\UniqueEmail;

class StaffRequest extends FormRequest
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
            'first_Name'     => 'required|string|max:255',
            'last_Name'     => 'required|string|max:255',
            'email'    => ['required', 'email', new UniqueEmail()],
            'password' => 'required|string|min:8',
            'phone'     => 'nullable|string|max:255',
            'date_of_birth'     => 'nullable|string|max:255',
            'job_title'     => 'nullable|string|max:255',
            'image'     => 'nullable|string|max:255',
            'role'     => 'required|string|in:staff',
            'skills'     => 'required|string|max:255',
            'join_date'     => 'required|string|max:255',
        ];

        return $rules;
    }
}
