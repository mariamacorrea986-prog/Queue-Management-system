<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:operator middleware protects the route
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'string', 'max:20'], // the university student ID number
            'route_id' => ['required', 'integer', Rule::exists('routes', 'id')->where('is_active', true)],
            // The reason is mandatory: it goes into the audit log, so a manual check-in can always be explained.
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'request_id' => ['nullable', 'uuid'],
        ];
    }
}
