<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// There is no user_id field: the student is always the logged-in user. Any user_id sent is ignored.
class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:student middleware protects the route
    }

    public function rules(): array
    {
        return [
            'route_id' => ['required', 'integer', Rule::exists('routes', 'id')->where('is_active', true)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0'],
            'arrived_at' => ['required', 'date'], // ISO 8601 with milliseconds, captured on the phone at the tap
            'request_id' => ['required', 'uuid'],
        ];
    }
}
