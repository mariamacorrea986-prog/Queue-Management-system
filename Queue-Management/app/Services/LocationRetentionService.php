<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

// Privacy: coordinates are kept only for location_retention_days (30 by default). The booking itself stays for the records.
class LocationRetentionService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function clearOld(): int
    {
        $days = Setting::getInt('location_retention_days', 30);
        $cutoff = now()->subDays($days)->format('Y-m-d H:i:s.v');

        $cleared = Booking::where('arrived_at', '<', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('latitude')->orWhereNotNull('longitude')->orWhereNotNull('accuracy_m');
            })
            ->update(['latitude' => null, 'longitude' => null, 'accuracy_m' => null]);

        if ($cleared > 0) {
            $this->audit->log(null, 'locations.cleared', null, null, ['count' => $cleared, 'retention_days' => $days]);
        }

        return $cleared;
    }
}
