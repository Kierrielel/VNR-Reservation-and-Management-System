<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['exclude_unless:decision,reject', 'required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('rejection_reason');
        if (is_string($reason)) {
            $this->merge(['rejection_reason' => trim($reason)]);
        }
    }
}
