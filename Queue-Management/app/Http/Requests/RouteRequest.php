<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $route = $this->route('route'); // null when creating

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('routes', 'name')->ignore($route)],
            'origin_station_id' => ['required', 'integer', 'exists:stations,id'],
            // A route that starts and ends at the same station makes no sense.
            'destination_station_id' => [
                'required', 'integer', 'exists:stations,id', 'different:origin_station_id',
                // The database also has this unique pair; checking here gives a friendly 422 instead of a crash.
                Rule::unique('routes', 'destination_station_id')
                    ->where('origin_station_id', $this->input('origin_station_id'))
                    ->ignore($route),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_station_id.different' => 'The destination must be a different station from the origin.',
            'destination_station_id.unique' => 'A route between these two stations already exists.',
        ];
    }
}
