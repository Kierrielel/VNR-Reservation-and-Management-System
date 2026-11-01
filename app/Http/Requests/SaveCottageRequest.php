<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCottageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('cottages', 'name')->ignore($this->route('cottage'))],
            'type' => ['required', Rule::in(['cottage', 'room'])],
            'capacity' => ['required', 'integer', 'min:1', 'max:65535'],
            'price_day' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'price_overnight' => ['present', 'nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::in(['available', 'occupied', 'unavailable'])],
        ];
    }
}
