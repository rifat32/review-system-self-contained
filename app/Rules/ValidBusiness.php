<?php

namespace App\Rules;

use App\Models\Business;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBusiness implements ValidationRule
{

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

        $query = Business::where('id', $value);


        if (!$query->exists()) {
            $message = 'The selected business is invalid.';
            $fail($message, null, ['id' => $value]);
        }
    }
}
