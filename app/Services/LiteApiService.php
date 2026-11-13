<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiteApiService
{
    /**
     * Check whether a LiteAPI key is configured.
     */
    public function configured(): bool
    {
        return (bool) config('services.liteapi.api_key');
    }

    /**
     * Search places (cities, regions, airports) anywhere in the world.
     *
     * Each result has a place_id that can be passed to rates() to find
     * hotels in that place.
     */
    public function places(string $query, ?string $type = null): array
    {
        $query = trim($query);

        if (!$this->configured() || mb_strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'liteapi_places_'.md5(mb_strtolower($query).'|'.$type);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client(12)->get(
                $this->url('/data/places'),
                array_filter([
                    'textQuery' => $query,
                    'type' => $type,
                ])
            );

            if (!$response->successful()) {
                Log::warning('LiteAPI places request failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $places = collect($response->json('data') ?? [])
                ->map(fn ($place) => [
                    'place_id' => data_get($place, 'placeId'),
                    'name' => data_get($place, 'displayName'),
                    'address' => data_get($place, 'formattedAddress'),
                    'types' => data_get($place, 'types', []),
                ])
                ->filter(fn ($place) => !empty($place['place_id']) && !empty($place['name']))
                ->values()
                ->all();

            // Only keep successful, non-empty responses in the cache.
            if (count($places) > 0) {
                Cache::put($cacheKey, $places, now()->addHours(24));
            }

            return $places;

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI places connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search live room rates.
     *
     * $location must contain one way of choosing hotels:
     *   ['placeId' => '...']
     *   ['hotelIds' => ['lp19e0b', ...]]
     *   ['cityName' => 'London', 'countryCode' => 'GB']
     *   ['latitude' => 51.5, 'longitude' => -0.12, 'radius' => 5000]
     *
     * $occupancies has one entry per room:
     *   [['adults' => 2, 'children' => [5, 9]]]
     *
     * $options can set: currency, guestNationality, limit, offset,
     * maxRatesPerHotel, sort, minRating, starRating and other filters
     * supported by LiteAPI.
     *
     * Returns ['rates' => [...], 'hotels' => [...]]. Prices are live,
     * so results are never cached.
     */
    public function rates(
        array $location,
        string $checkin,
        string $checkout,
        array $occupancies,
        array $options = []
    ): array {
        $empty = ['rates' => [], 'hotels' => []];

        if (!$this->configured() || count($location) === 0 || count($occupancies) === 0) {
            return $empty;
        }

        $body = array_merge(
            [
                'currency' => 'GBP',
                'guestNationality' => 'GB',
                'timeout' => 8,
                'includeHotelData' => true,
            ],
            $options,
            $location,
            [
                'checkin' => $checkin,
                'checkout' => $checkout,
                'occupancies' => array_values($occupancies),
            ]
        );

        try {
            $response = $this->client(25)->post($this->url('/hotels/rates'), $body);

            $payload = $response->json();

            // LiteAPI can return an error object, even with HTTP 200.
            if (!$response->successful() || !is_array($payload) || isset($payload['error'])) {
                $code = data_get($payload, 'error.code');

                // Code 2001 means no availability, which is not a fault.
                if ($code !== 2001) {
                    Log::warning('LiteAPI rates request failed.', [
                        'status' => $response->status(),
                        'code' => $code,
                        'message' => data_get($payload, 'error.message')
                            ?? data_get($payload, 'error.description'),
                    ]);
                }

                return $empty;
            }

            return [
                'rates' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
                'hotels' => is_array($payload['hotels'] ?? null) ? $payload['hotels'] : [],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI rates connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * Build an HTTP client that sends the API key.
     */
    protected function client(int $timeout): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => (string) config('services.liteapi.api_key'),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout);
    }

    /**
     * Build the full URL for an API path.
     */
    protected function url(string $path): string
    {
        return rtrim((string) config('services.liteapi.base_url'), '/').$path;
    }
}