<?php

namespace App\Services;

use App\Contracts\PlaceProvider;

class OpenTripMapPlaceProvider implements PlaceProvider
{
    public function __construct(
        protected OpenTripMapService $openTripMap
    ) {
    }

    public function name(): string
    {
        return 'opentripmap';
    }

    public function isConfigured(): bool
    {
        return $this->openTripMap->configured();
    }

    /**
     * Search attractions near a destination's coordinates.
     *
     * Uses these entries of the normalized criteria:
     * [
     *     'keyword' => 'London',      // optional, used as the city label
     *     'latitude' => 51.5072,      // required
     *     'longitude' => -0.1276,     // required
     * ]
     */
    public function search(array $criteria): array
    {
        $city = $criteria['keyword'] ?? null;
        $latitude = $criteria['latitude'] ?? null;
        $longitude = $criteria['longitude'] ?? null;

        $city = is_string($city) && trim($city) !== ''
            ? trim($city)
            : null;

        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude) ||
            (float) $latitude < -90 ||
            (float) $latitude > 90 ||
            (float) $longitude < -180 ||
            (float) $longitude > 180
        ) {
            return [];
        }

        $attractions = $this->openTripMap->attractions(
            (float) $latitude,
            (float) $longitude
        );

        return array_map(
            fn ($place) => [
                'name' => $place['name'] ?? 'Unnamed attraction',
                'city' => $city,
                'xid' => $place['xid'] ?? null,
                'category' => $place['kinds'] ?? null,
                'distance' => $place['distance'] ?? null,
                'source' => 'opentripmap',
            ],
            $attractions
        );
    }
}