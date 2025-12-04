<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
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
        return [
            'description' => 'required|string|max:255',
            // 'rate' => 'nullable|numeric|min:1|max:5',
            'comment' => 'nullable|string',
            'values' => 'required|array|min:1',
            'values.*.question_id' => 'required|integer|exists:questions,id',
            'values.*.tag_id' => 'required|integer|exists:tags,id',
            'values.*.star_id' => 'required|integer',
            'survey_id' => 'nullable|integer|exists:surveys,id',
            'is_overall' => 'nullable|boolean',
            'staff_id' => 'nullable|integer|exists:users,id',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'comment'          => $this->string('comment', ""),
        ]);
    }
}
