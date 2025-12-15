<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;

class UniqueEmail implements ValidationRule
{
    protected $userId;

    /**
     * Create a new rule instance.
     *
     * @param int|null $userId The user ID to ignore during validation (for updates)
     */
    public function __construct($userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = User::where('email', $value);

        // If updating, exclude the current user from the check
        if ($this->userId) {
            $query->where('id', '!=', $this->userId);
        }

        if ($query->exists()) {
            $fail('The email has already been taken.', null);
        }
    }
}
