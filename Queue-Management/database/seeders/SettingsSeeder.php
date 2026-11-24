<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Defaults from section 9.4 of the overview, plus trip_min_gap_minutes (added in Stage 3).
        // updateOrCreate makes the seeder safe to run twice.
        $defaults = [
            'grace_window_minutes' => ['5', 'How old (minutes) an arrival time may be and still be accepted'],
            'confirmation_window_minutes' => ['5', 'Minutes a booking waits before it gets its queue number'],
            'max_gps_accuracy_m' => ['50', 'Worst GPS accuracy (metres) accepted at check-in'],
            'call_deadline_minutes' => ['5', 'Minutes a called student has to reach the boarding point'],
            'max_skips' => ['2', 'Skipped calls allowed before a booking expires'],
            'location_retention_days' => ['30', 'Days before stored coordinates are erased'],
            'trip_min_gap_minutes' => ['60', 'Minimum minutes between two trips of the same bus or driver'],
        ];

        foreach ($defaults as $name => [$value, $description]) {
            Setting::updateOrCreate(['name' => $name], ['value' => $value, 'description' => $description]);
        }
    }
}
