<?php

namespace App\Domain\Location;

/**
 * Geo abstraction — never hardcode a map vendor. Configure via GEO_DRIVER.
 * Default haversine works on plain MySQL. Map rendering is a frontend concern.
 */
interface GeoServiceInterface
{
    /** Haversine distance in km. */
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float;

    /** Apply a radius filter to a Service query by partner coordinates. */
    public function applyRadius($query, float $lat, float $lng, float $radiusKm): mixed;
}

class GeoService implements GeoServiceInterface
{
    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function applyRadius($query, float $lat, float $lng, float $radiusKm): mixed
    {
        $r = 6371;
        $having = "(6371 * 2 * atan2(sqrt(pow(sin(radians(partners.lat - ?) / 2), 2) + cos(radians(?)) * cos(radians(partners.lat)) * pow(sin(radians(partners.lng - ?) / 2), 2)), sqrt(1 - pow(sin(radians(partners.lat - ?) / 2), 2) + cos(radians(?)) * cos(radians(partners.lat)) * pow(sin(radians(partners.lng - ?) / 2), 2)))) <= ?";

        return $query
            ->join('partners', 'partners.id', '=', 'services.partner_id')
            ->addSelect('services.*')
            ->whereNotNull('partners.lat')
            ->whereRaw($having, [$lat, $lat, $lng, $lat, $lat, $lng, $radiusKm]);
    }
}
