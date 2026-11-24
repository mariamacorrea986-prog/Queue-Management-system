<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriorityClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The student role middleware protects this endpoint.
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['pregnant', 'with_child', 'disability', 'sick'])],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Please attach a supporting document.',
            'document.mimes' => 'The document must be a PDF, JPG, JPEG or PNG file.',
            'document.max' => 'The document must not be larger than 5 MB.',
        ];
    }
}
