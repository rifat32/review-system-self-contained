<?php

namespace App\Rules;

use App\Models\Business;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBusiness implements ValidationRule
{
    protected $userId;
    protected $checkOwnership;

    public function __construct(?int $userId = null, ?bool $checkOwnership = true)
    {
        $this->userId = $userId;
        $this->checkOwnership = $checkOwnership;
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

        $query = Business::where('id', $value);

        if ($this->checkOwnership && $this->userId) {
            $query->where('OwnerID', $this->userId);
        }

        if (!$query->exists()) {
            $message = $this->checkOwnership && $this->userId
                ? 'The selected business is invalid or you do not own it.'
                : 'The selected business is invalid.';
            $fail($message);
        }
    }
}
