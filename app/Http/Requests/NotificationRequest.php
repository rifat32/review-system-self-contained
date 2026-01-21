<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationRequest extends FormRequest
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
        if ($this->isMethod('patch')) {
            return [
                'message' => 'required|string',
                'status' => 'nullable|string',
            ];
        }

        return [
            'title' => 'nullable|string',
            'message' => 'required|string',
            'receiver_id' => 'required|integer|exists:users,id',
            'business_id' => 'nullable|integer|exists:businesses,id',
            'status' => 'nullable|string|in:unread,read',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'entity_id' => 'nullable|integer',
        ];
    }
}
