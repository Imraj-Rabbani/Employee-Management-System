<?php

namespace App\Modules\Payroll\Services;

/**
 * Distance between two GPS points, in metres (haversine formula: the earth as a sphere).
 * Plenty precise for "is this person within 200 m of the office?".
 */
class GeoDistance
{
    private const EARTH_RADIUS_M = 6371000;

    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
