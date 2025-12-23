<?php

namespace App\Http\Requests;

use App\Models\Question;
use App\Rules\ValidBusiness;
use App\Rules\ValidQuestionCategory;
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
            'show_in_guest_user' => 'required|boolean',
            'show_in_user'       => 'required|boolean',
            'type'               => ['nullable', 'string', Rule::in(array_values(Question::QUESTION_TYPES))],
            'is_overall'         => 'required|boolean',
            'question_sub_category_ids' => 'nullable|array', // Change to array
            'question_sub_category_ids.*' => 'integer|exists:question_categories,id',
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
            'show_in_guest_user' => $this->boolean('show_in_guest_user'),
            'show_in_user'       => $this->boolean('show_in_user'),
            'is_overall'         => $this->boolean('is_overall', false),
            'type'               => $this->filled('type') ? $this->type : 'star',
        ]);
    }
}
