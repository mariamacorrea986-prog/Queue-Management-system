<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'expired'])],
            'q' => ['sometimes', 'string', 'max:100'], // search by student name or student ID
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
