<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenTripMapService
{
    /**
     * Check whether OpenTripMap API credentials are configured.
     */
    public function configured(): bool
    {
        return (bool) config('services.opentripmap.api_key');
    }

    /**
     * Search for tourist attractions near coordinates.
     */
    public function attractions(
        float $latitude,
        float $longitude,
        int $radius = 5000,
        int $limit = 20
    ): array {
        $apiKey = config('services.opentripmap.api_key');

        if (!$apiKey) {
            return [];
        }

        $radius = max(100, min($radius, 20000));
        $limit = max(1, min($limit, 50));

        $cacheKey = 'opentripmap_'.md5(
            implode('|', [
                round($latitude, 4),
                round($longitude, 4),
                $radius,
                $limit,
            ])
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours(12),
            function () use ($apiKey, $latitude, $longitude, $radius, $limit) {
                try {
                    $response = Http::acceptJson()
                        ->timeout(20)
                        ->get(
                            rtrim(config('services.opentripmap.base_url'), '/')
                            .'/places/radius',
                            [
                                'radius' => $radius,
                                'lon' => $longitude,
                                'lat' => $latitude,
                                'limit' => $limit,
                                'rate' => 2,
                                'format' => 'json',
                                'apikey' => $apiKey,
                            ]
                        );

                    if (!$response->successful()) {
                        Log::warning('OpenTripMap API request failed.', [
                            'status' => $response->status(),
                        ]);

                        return [];
                    }

                    return collect($response->json() ?? [])
                        ->map(fn ($place) => [
                            'xid' => data_get($place, 'xid'),
                            'name' => data_get($place, 'name'),
                            'kinds' => data_get($place, 'kinds'),
                            'distance' => data_get($place, 'dist'),
                            'source' => 'opentripmap',
                        ])
                        ->filter(fn ($place) => !empty($place['xid']))
                        ->values()
                        ->all();

                } catch (\Throwable $exception) {
                    Log::warning('OpenTripMap connection error.', [
                        'message' => $exception->getMessage(),
                    ]);

                    return [];
                }
            }
        );
    }

    /**
     * Retrieve detailed information about an attraction.
     */
    public function details(string $xid): ?array
    {
        $apiKey = config('services.opentripmap.api_key');

        if (!$apiKey || trim($xid) === '') {
            return null;
        }

        return Cache::remember(
            'opentripmap_details_'.md5($xid),
            now()->addHours(24),
            function () use ($apiKey, $xid) {
                try {
                    $response = Http::acceptJson()
                        ->timeout(20)
                        ->get(
                            rtrim(config('services.opentripmap.base_url'), '/')
                            .'/places/xid/'.rawurlencode($xid),
                            ['apikey' => $apiKey]
                        );

                    if (!$response->successful()) {
                        return null;
                    }

                    $place = $response->json();

                    return [
                        'xid' => data_get($place, 'xid'),
                        'name' => data_get($place, 'name'),
                        'description' => data_get($place, 'wikipedia_extracts.text'),
                        'image' => data_get($place, 'preview.source'),
                        'url' => data_get($place, 'otm'),
                        'latitude' => data_get($place, 'point.lat'),
                        'longitude' => data_get($place, 'point.lon'),
                        'kinds' => data_get($place, 'kinds'),
                        'source' => 'opentripmap',
                    ];
                } catch (\Throwable $exception) {
                    Log::warning('OpenTripMap details request failed.', [
                        'xid' => $xid,
                        'message' => $exception->getMessage(),
                    ]);

                    return null;
                }
            }
        );
    }
}
