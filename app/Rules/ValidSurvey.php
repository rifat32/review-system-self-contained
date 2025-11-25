<?php

namespace App\Rules;

use App\Models\Survey;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSurvey implements ValidationRule
{
    protected $businessId;

    public function __construct($businessId = null)
    {
        $this->businessId = $businessId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return; // Allow null values
        }

        $query = Survey::where('id', $value);

        if ($this->businessId) {
            $query->where('business_id', $this->businessId);
        }

        if (!$query->exists()) {
            $fail('The selected survey is invalid or does not belong to this business.');
        }
    }
}
