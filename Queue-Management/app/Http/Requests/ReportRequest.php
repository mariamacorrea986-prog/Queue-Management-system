<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

// Date range for the reports. Without dates, the last 7 days (today included) are used.
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'route_id' => ['sometimes', 'integer', 'exists:routes,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isEmpty() && Carbon::parse($this->fromDate())->diffInDays(Carbon::parse($this->toDate())) > 366) {
                $validator->errors()->add('to', 'The date range cannot be longer than one year.');
            }
        });
    }

    public function fromDate(): string
    {
        return $this->input('from', today()->subDays(6)->toDateString());
    }

    public function toDate(): string
    {
        return $this->input('to', today()->toDateString());
    }

    public function routeId(): ?int
    {
        return $this->filled('route_id') ? $this->integer('route_id') : null;
    }
}
