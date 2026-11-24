<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the filters of GET /trips (FR-33: filter trips by route and date).
class TripFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(['scheduled', 'boarding', 'departed', 'cancelled'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
