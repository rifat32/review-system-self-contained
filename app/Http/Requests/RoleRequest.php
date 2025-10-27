<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{


    public function authorize()
    {
        return true;
    }



    public function rules()
    {
        return [
            "name" => "required|unique:roles,name",
            "is_default_for_business" => "required|boolean",
            "permissions" => "present|array",
            "description" => "nullable|string"
        ];
    }








}
