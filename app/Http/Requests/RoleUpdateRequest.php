<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
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



    public function rules()
    {
        return [
            "id" => "required",
            "name" => 'required|string|unique:roles,name,' . $this->id . ',id',
            "permissions" => "required",
            "description" => "nullable|string"
        ];
    }








}
