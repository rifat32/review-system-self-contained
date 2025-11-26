<?php

namespace App\Http\Requests;

use App\Rules\ValidBusiness;
use App\Rules\ValidQuestion;
use Illuminate\Foundation\Http\FormRequest;

class SetOverallQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the ValidBusiness rule
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => new ValidQuestion($this->input('business_id')),
            'business_id' => new ValidBusiness($this->user() ? $this->user()->id : null, true),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'question_ids.required' => 'At least one question ID is required.',
        ];
    }
}
