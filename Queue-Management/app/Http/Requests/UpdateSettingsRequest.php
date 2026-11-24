<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

// The body is simply {"setting_name": value, ...}. Send only the settings you want to change.
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (SettingService::LIMITS as $name => [$min, $max]) {
            $rules[$name] = ['sometimes', 'required', 'integer', "between:{$min},{$max}"];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Reject names we do not know, instead of silently ignoring a typo.
            foreach (array_diff(array_keys($this->all()), array_keys(SettingService::LIMITS)) as $unknown) {
                $validator->errors()->add($unknown, 'This is not a known setting.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Fairness rule: a booking is confirmed only after the confirmation window. If that were SHORTER than the
            // grace window (how late a check-in may arrive), a student could get a number before an earlier
            // arrival has reached the server, and that arrival would then be unfairly behind them.
            $current = fn (string $name, int $default) => (int) (Setting::where('name', $name)->value('value') ?? $default);
            $grace = (int) $this->input('grace_window_minutes', $current('grace_window_minutes', 5));
            $confirmation = (int) $this->input('confirmation_window_minutes', $current('confirmation_window_minutes', 5));

            if ($confirmation < $grace) {
                $field = $this->has('confirmation_window_minutes') ? 'confirmation_window_minutes' : 'grace_window_minutes';
                $validator->errors()->add($field, 'The confirmation window must be at least as long as the grace window, otherwise a student could be given a number before an earlier arrival has reached the server.');
            }
        });
    }
}
