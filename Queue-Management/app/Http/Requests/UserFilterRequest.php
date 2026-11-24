<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(['student', 'driver', 'operator', 'admin'])],
            'is_active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:100'], // name, email or student ID
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
