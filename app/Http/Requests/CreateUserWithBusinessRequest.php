<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserWithBusinessRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            // User fields
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'first_Name' => ['required', 'string', 'max:255'],
            'last_Name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],

            // Business fields
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string'],
            'business_postcode' => ['required', 'string', 'max:20'],
            'business_EmailAddress' => ['nullable', 'email'],
            'business_GoogleMapApi' => ['nullable', 'string'],
            'business_homeText' => ['nullable', 'string'],
            'business_AdditionalInformation' => ['nullable', 'string'],
            'business_Webpage' => ['nullable', 'url'],
            'business_PhoneNumber' => ['nullable', 'string', 'max:20'],
            'business_About' => ['nullable', 'string'],
            'business_Layout' => ['nullable', 'string'],

            // Review settings
            'Is_guest_user' => ['nullable', 'boolean'],
            'is_review_silder' => ['nullable', 'boolean'],
            'review_only' => ['nullable', 'boolean'],
            'review_type' => ['nullable', 'string', 'in:emoji,star'],
            'google_map_iframe' => ['nullable', 'string'],
            'show_image' => ['nullable', 'string'],

            // Images
            'header_image' => ['nullable', 'string'],
            'rating_page_image' => ['nullable', 'string'],
            'placeholder_image' => ['nullable', 'string'],

            // Colors
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'client_primary_color' => ['nullable', 'string', 'max:20'],
            'client_secondary_color' => ['nullable', 'string', 'max:20'],
            'client_tertiary_color' => ['nullable', 'string', 'max:20'],

            // Reports
            'user_review_report' => ['nullable', 'boolean'],
            'guest_user_review_report' => ['nullable', 'boolean'],

            // Business schedule
            'times' => ['required', 'array', 'min:1'],
            'times.*.day' => ['required', 'integer', 'between:0,6'],
            'times.*.is_weekend' => ['required', 'boolean'],
            'times.*.time_slots' => ['required', 'array', 'min:1'],
            'times.*.time_slots.*.start_at' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $this->validateTimeSlot($attribute, $value, $fail);
                },
            ],
            'times.*.time_slots.*.end_at' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $this->validateTimeSlot($attribute, $value, $fail);
                },
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered',
            'password.min' => 'Password must be at least 6 characters',
            'business_name.required' => 'Business name is required',
            'business_address.required' => 'Business address is required',
            'business_postcode.required' => 'Business postcode is required',
            'times.required' => 'Business hours are required',
            'times.*.day.between' => 'Day must be between 0 (Sunday) and 6 (Saturday)',
        ];
    }

    /**
     * Validate time slot based on schedule type and weekend status.
     */
    private function validateTimeSlot(string $attribute, $value, $fail): void
    {
        // Extract the times array index
        preg_match('/times\.(\d+)\./', $attribute, $matches);
        $index = $matches[1] ?? null;

        if ($index === null) {
            return;
        }

        $times = $this->input('times', []);
        $isWeekend = $times[$index]['is_weekend'] ?? false;
        $type = $this->input('type');

        // Require time slots for scheduled type on non-weekend days
        if ($type === 'scheduled' && !$isWeekend && empty($value)) {
            $fail("The {$attribute} is required for scheduled business days.");
        }
    }
}
