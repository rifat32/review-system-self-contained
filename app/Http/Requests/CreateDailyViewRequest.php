<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDailyViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'view_date' => 'required|date|date_format:d-m-Y',
            'daily_views' => 'required|integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'view_date.required' => 'The view date is required.',
            'view_date.date' => 'The view date must be a valid date.',
            'view_date.date_format' => 'The view date must be in DD-MM-YYYY format.',
            'daily_views.required' => 'The daily views count is required.',
            'daily_views.integer' => 'The daily views must be a valid number.',
            'daily_views.min' => 'The daily views cannot be negative.',
        ];
    }
}
