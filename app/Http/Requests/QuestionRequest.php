<?php

namespace App\Http\Requests;

use App\Models\Question;
use App\Rules\ValidBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Allow only authenticated users with proper role or business ownership
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {

        return [
            'question'           => 'required|string|max:255',
            'business_id'        => ['nullable', 'integer', new ValidBusiness()],
            'is_active'          => 'required|boolean',
            'show_in_guest_user' => 'sometimes|boolean',
            'show_in_user'       => 'sometimes|boolean',
            'survey_name'        => 'nullable|string|max:255',
            'survey_id'          => 'nullable|integer|exists:surveys,id',
            'type'               => ['nullable', 'string', Rule::in(array_values(Question::QUESTION_TYPES))],
            'is_overall'         => 'sometimes|boolean',
            'is_staff'           => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'The question text is required.',
            'type.in'           => "The type must be one of: " . implode(', ', ['star', 'emoji', 'numbers', 'heart']),
            'business_id.exists' => 'The selected business does not exist.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'is_active'          => $this->boolean('is_active'),
            'show_in_guest_user' => $this->boolean('show_in_guest_user'),
            'show_in_user'       => $this->boolean('show_in_user'),
            'is_overall'         => $this->boolean('is_overall', false),
            'is_staff'           => $this->boolean('is_staff', false),
            'type'               => $this->filled('type') ? $this->type : 'star',
        ]);
    }
}
