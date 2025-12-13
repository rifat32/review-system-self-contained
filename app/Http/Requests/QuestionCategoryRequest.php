<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\QuestionCategory;

class QuestionCategoryRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_question_category_id' => 'nullable|integer|exists:question_categories,id',
        ];

        // If updating, add ID validation
        // if ($this->isMethod('put') || $this->isMethod('patch')) {
        //     $rules['id'] = 'required|integer|exists:question_categories,id';
        // }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->filled('parent_question_category_id')) {
                $parentCategory = QuestionCategory::find($this->parent_question_category_id);

                if (!$parentCategory) {
                    $validator->errors()->add('parent_question_category_id', 'The selected parent category does not exist.');
                    return;
                }

                if (!$parentCategory->canHaveChildren()) {
                    $validator->errors()->add('parent_question_category_id', 'Sub-categories cannot have their own sub-categories. Only one level of nesting is allowed.');
                }

                // Prevent self-reference
                if ($this->isMethod('put') || $this->isMethod('patch')) {
                    if ($this->id == $this->parent_question_category_id) {
                        $validator->errors()->add('parent_question_category_id', 'A category cannot be its own parent.');
                    }
                }
            }
        });
    }
    public function messages()
    {
        return [
            'title.required' => 'The category title is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.string' => 'The description must be a string.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'parent_question_category_id.integer' => 'The parent category ID must be an integer.',
            'parent_question_category_id.exists' => 'The selected parent category does not exist.',
            'id.required' => 'The category ID is required for updates.',
            'id.integer' => 'The category ID must be an integer.',
            'id.exists' => 'The selected category does not exist.',
        ];
    }
}
