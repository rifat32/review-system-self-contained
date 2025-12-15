<?php

namespace App\Rules;

use App\Models\QuestionCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidQuestionCategory implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!QuestionCategory::where('id', $value)->exists()) {
            $fail('The selected question category is invalid.', null);
        }
    }
}
