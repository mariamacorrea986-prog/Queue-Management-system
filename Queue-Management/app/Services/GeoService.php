<?php

namespace App\Services;

class GeoService
{
    private const EARTH_RADIUS_M = 6371000;

    // Haversine formula: the straight-line distance in metres between two GPS points on the Earth's surface.
    // The phone uses the same formula (in JavaScript), so both sides agree on "inside the station area".
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(sqrt($a));
    }
}
