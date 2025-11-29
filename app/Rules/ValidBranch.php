<?php

namespace App\Rules;

use App\Models\Branch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBranch implements ValidationRule
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

        $branch = Branch::with('business')->find($value);

        if (!$branch) {
            $fail('The selected branch is invalid.');
            return;
        }

        if ($this->checkOwnership && $this->userId) {
            if ($branch->business->OwnerID != $this->userId) {
                $fail('You do not own the business this branch belongs to.');
            }
        }
    }
}
