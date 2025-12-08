<?php

namespace App\Http\Requests;

use App\Rules\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
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

    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'first_Name'    => ['nullable', 'string', 'max:255'],
            'last_Name'     => ['nullable', 'string', 'max:255'],
            'email'         => ['nullable', 'email', new UniqueEmail($id)],
            'phone'         => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'string'],
            'job_title'     => ['nullable', 'string', 'max:255'],
            'image'         => ['nullable', 'string', 'max:255'],
            'role'          => ['nullable', 'string', Rule::in(['business_staff'])],
            'skills'     => 'required|string|max:255',
            'join_date'     => 'required|string|max:255',
        ];
    }
}
