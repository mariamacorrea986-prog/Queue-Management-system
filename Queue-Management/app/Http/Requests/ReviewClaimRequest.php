<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Used by approve and reject. valid_until is only used when approving.
class ReviewClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            // Goes into the audit log. It must never contain medical details.
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
