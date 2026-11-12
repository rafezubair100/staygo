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
     * Search attractions using a destination and its coordinates.
     *
     * Expected keyword format:
     * London|51.5072|-0.1276
     */
    public function search(string $keyword): array
    {
        $parts = explode('|', $keyword);

        if (count($parts) !== 3) {
            return [];
        }

        [$city, $latitude, $longitude] = $parts;

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
