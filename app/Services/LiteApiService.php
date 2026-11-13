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
     * Fetch the full details of one hotel: description, photos,
     * facilities, rooms and policies.
     *
     * Returns null when the hotel cannot be loaded. Details change
     * rarely, so successful responses are cached for 12 hours.
     */
    public function hotel(string $hotelId): ?array
    {
        $hotelId = trim($hotelId);

        if (!$this->configured() || $hotelId === '') {
            return null;
        }

        $cacheKey = 'liteapi_hotel_'.md5($hotelId);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client(15)->get($this->url('/data/hotel'), [
                'hotelId' => $hotelId,
                'timeout' => 6,
            ]);

            $data = $response->json('data');

            if (!$response->successful() || !is_array($data) || empty($data['id'])) {
                Log::warning('LiteAPI hotel details request failed.', [
                    'status' => $response->status(),
                    'hotel_id' => $hotelId,
                ]);

                return null;
            }

            $hotel = [
                'id' => $data['id'],
                'name' => $data['name'] ?? 'Hotel',
                'description' => $this->safeHtml($data['hotelDescription'] ?? null),
                'important_information' => $this->plainText($data['hotelImportantInformation'] ?? null),
                'checkin_from' => data_get($data, 'checkinCheckoutTimes.checkin_start'),
                'checkout_until' => data_get($data, 'checkinCheckoutTimes.checkout'),
                'main_photo' => $data['main_photo'] ?? null,
                'images' => collect($data['hotelImages'] ?? [])
                    ->sortBy(fn ($image) => data_get($image, 'order', PHP_INT_MAX))
                    ->map(fn ($image) => [
                        'url' => data_get($image, 'urlHd') ?: data_get($image, 'url'),
                        'thumbnail' => data_get($image, 'url') ?: data_get($image, 'urlHd'),
                        'caption' => data_get($image, 'caption'),
                    ])
                    ->filter(fn ($image) => !empty($image['url']))
                    ->values()
                    ->all(),
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'stars' => $data['starRating'] ?? null,
                'rating' => $data['rating'] ?? null,
                'review_count' => $data['reviewCount'] ?? null,
                'latitude' => data_get($data, 'location.latitude'),
                'longitude' => data_get($data, 'location.longitude'),
                'facilities' => collect($data['hotelFacilities'] ?? [])
                    ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                    ->map(fn ($name) => trim($name))
                    ->unique()
                    ->values()
                    ->all(),
                'rooms' => collect($data['rooms'] ?? [])
                    ->filter(fn ($room) => isset($room['id']))
                    ->mapWithKeys(fn ($room) => [
                        (string) $room['id'] => [
                            'id' => $room['id'],
                            'name' => $room['roomName'] ?? null,
                            'description' => $this->plainText($room['description'] ?? null),
                            'size' => $room['roomSizeSquare'] ?? null,
                            'max_occupancy' => $room['maxOccupancy'] ?? null,
                            'beds' => collect($room['bedTypes'] ?? [])
                                ->map(fn ($bed) => trim(
                                    (data_get($bed, 'quantity') ?: 1).' x '.data_get($bed, 'bedType', 'bed')
                                ))
                                ->values()
                                ->all(),
                            'amenities' => collect($room['roomAmenities'] ?? [])
                                ->sortBy(fn ($amenity) => data_get($amenity, 'sort', PHP_INT_MAX))
                                ->pluck('name')
                                ->filter()
                                ->values()
                                ->all(),
                            'photos' => collect($room['photos'] ?? [])
                                ->sortByDesc(fn ($photo) => (bool) data_get($photo, 'mainPhoto'))
                                ->map(fn ($photo) => data_get($photo, 'url') ?: data_get($photo, 'hd_url'))
                                ->filter()
                                ->values()
                                ->all(),
                        ],
                    ])
                    ->all(),
                'policies' => collect($data['policies'] ?? [])
                    ->map(fn ($policy) => [
                        'name' => data_get($policy, 'name'),
                        'description' => $this->plainText(data_get($policy, 'description')),
                    ])
                    ->filter(fn ($policy) => !empty($policy['name']) && !empty($policy['description']))
                    ->values()
                    ->all(),
            ];

            Cache::put($cacheKey, $hotel, now()->addHours(12));

            return $hotel;

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI hotel details connection error.', [
                'hotel_id' => $hotelId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Keep only simple formatting tags, with every attribute removed,
     * so supplier text is safe to show as HTML.
     */
    protected function safeHtml(mixed $html): ?string
    {
        if (!is_string($html) || trim($html) === '') {
            return null;
        }

        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i>');

        return trim(preg_replace('/<(\/?)([a-zA-Z]+)[^>]*>/', '<$1$2>', $html));
    }

    /**
     * Remove all HTML and tidy the white space.
     */
    protected function plainText(mixed $text): ?string
    {
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
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