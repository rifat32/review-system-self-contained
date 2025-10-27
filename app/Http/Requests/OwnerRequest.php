<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OwnerRequest extends FormRequest
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
            'email' => 'email|required|unique:users,email',
            'password' => 'required|string|min:6',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'last_Name' => 'nullable',
            'type' => 'required'
        ];
    }
}
