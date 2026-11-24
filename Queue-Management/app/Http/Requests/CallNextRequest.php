<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CallNextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role middleware and TripPolicy protect the route
    }

    public function rules(): array
    {
        // Optional: call fewer students than the free seats. Without it, the driver calls as many as there are seats.
        return ['count' => ['nullable', 'integer', 'between:1,200']];
    }
}
