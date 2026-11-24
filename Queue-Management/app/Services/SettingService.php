<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettingService
{
    // The allowed range for each setting: [minimum, maximum]. Also tells the admin screen which settings exist.
    public const LIMITS = [
        'grace_window_minutes' => [1, 60],
        'confirmation_window_minutes' => [1, 60],
        'max_gps_accuracy_m' => [5, 500],
        'call_deadline_minutes' => [1, 60],
        'max_skips' => [1, 10],
        'location_retention_days' => [1, 365],
        'trip_min_gap_minutes' => [1, 720],
    ];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function update(array $values, User $actor, ?string $ip): void
    {
        $changes = DB::transaction(function () use ($values, $actor, $ip) {
            $changes = [];

            foreach ($values as $name => $value) {
                $setting = Setting::firstOrNew(['name' => $name]);
                $old = $setting->value;
                $new = (string) $value; // the settings table stores every value as text

                if ($old === $new) {
                    continue;
                }

                $setting->value = $new;
                $setting->save();
                $changes[$name] = ['from' => $old, 'to' => $new];
            }

            if ($changes !== []) {
                $this->audit->log($actor, 'settings.updated', null, null, ['changes' => $changes], $ip);
            }

            return $changes;
        });

        // After the commit, drop the cached copies so the new values apply immediately.
        foreach (array_keys($changes) as $name) {
            Setting::forgetCache($name);
        }
    }
}
