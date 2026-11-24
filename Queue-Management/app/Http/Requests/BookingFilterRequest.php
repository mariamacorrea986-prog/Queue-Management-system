<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Filters for GET /bookings (FR-33: by student ID, name or status).
class BookingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date_format:Y-m-d'], // the queue date
            'status' => ['sometimes', Rule::in(['provisional', 'confirmed', 'called', 'boarded', 'expired', 'cancelled'])],
            'lane' => ['sometimes', Rule::in(['general', 'priority'])],
            'q' => ['sometimes', 'string', 'max:100'], // student ID or name
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
