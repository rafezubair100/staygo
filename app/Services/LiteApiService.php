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
     * Check a chosen offer before booking it.
     *
     * LiteAPI confirms the room is still available and returns the
     * final price. Returns ['ok' => bool, 'error' => ?string,
     * 'data' => ?array]. The prebook_id in the data is needed by book().
     */
    public function prebook(string $offerId): array
    {
        $offerId = trim($offerId);

        if (!$this->configured() || $offerId === '') {
            return $this->failure('This offer could not be checked.');
        }

        try {
            $response = $this->client(40)->post(
                $this->bookUrl('/rates/prebook').'?timeout=30',
                [
                    'offerId' => $offerId,
                    'usePaymentSdk' => false,
                ]
            );

            $payload = $response->json();
            $data = data_get($payload, 'data');

            if (!$response->successful() || !is_array($data) || empty($data['prebookId'])) {
                $code = data_get($payload, 'error.code');

                Log::warning('LiteAPI prebook request failed.', [
                    'status' => $response->status(),
                    'code' => $code,
                    'message' => data_get($payload, 'error.message')
                        ?? data_get($payload, 'error.description'),
                ]);

                return $this->failure(
                    $code === 4040
                        ? 'This price has expired. Please go back and choose the room again.'
                        : 'This room is no longer available. Please choose another room.'
                );
            }

            $rate = data_get($data, 'roomTypes.0.rates.0', []);

            return [
                'ok' => true,
                'error' => null,
                'data' => [
                    'prebook_id' => $data['prebookId'],
                    'hotel_id' => $data['hotelId'] ?? null,
                    'check_in' => $data['checkin'] ?? null,
                    'check_out' => $data['checkout'] ?? null,
                    'price' => is_numeric($data['price'] ?? null) ? round((float) $data['price'], 2) : null,
                    'currency' => $data['currency'] ?? null,
                    'price_change_percent' => (float) ($data['priceDifferencePercent'] ?? 0),
                    'cancellation_changed' => (bool) ($data['cancellationChanged'] ?? false),
                    'board_changed' => (bool) ($data['boardChanged'] ?? false),
                    'room_name' => $rate['name'] ?? null,
                    'board' => $rate['boardName'] ?? null,
                    'room_count' => count(data_get($data, 'roomTypes.0.rates', [])),
                    'refundable' => data_get($rate, 'cancellationPolicies.refundableTag') === 'RFN',
                    'cancel_by' => data_get($rate, 'cancellationPolicies.cancelPolicyInfos.0.cancelTime'),
                    // Fees that are not in the price and are paid at the hotel.
                    'pay_at_hotel' => collect(data_get($rate, 'retailRate.taxesAndFees') ?? [])
                        ->filter(fn ($fee) => data_get($fee, 'included') === false)
                        ->map(fn ($fee) => [
                            'description' => data_get($fee, 'description'),
                            'amount' => data_get($fee, 'amount'),
                            'currency' => data_get($fee, 'currency'),
                        ])
                        ->values()
                        ->all(),
                    'terms' => $this->plainText($data['termsAndConditions'] ?? null),
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI prebook connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The booking service could not be reached. Please try again.');
        }
    }

    /**
     * Confirm a booking for a prebooked offer.
     *
     * $holder: ['firstName', 'lastName', 'email', 'phone']
     * $guests: one entry per room, each with
     *          ['occupancyNumber', 'firstName', 'lastName', 'email']
     * $clientReference: a unique reference for this booking attempt,
     *          which stops the same booking being made twice.
     *
     * Only sandbox keys are accepted. Sandbox bookings are simulated
     * and nothing is charged. Live payments are not set up yet, so a
     * production key is refused here.
     *
     * Returns ['ok' => bool, 'error' => ?string, 'data' => ?array].
     */
    public function book(string $prebookId, array $holder, array $guests, string $clientReference): array
    {
        if (!$this->configured() || trim($prebookId) === '') {
            return $this->failure('This booking could not be started.');
        }

        if (!$this->sandbox()) {
            return $this->failure('Online payment is not available yet, so this booking was not made.');
        }

        try {
            $response = $this->client(60)->post(
                $this->bookUrl('/rates/book').'?timeout=45',
                [
                    'prebookId' => trim($prebookId),
                    'clientReference' => $clientReference,
                    'holder' => $holder,
                    'guests' => array_values($guests),
                    'payment' => [
                        'method' => 'ACC_CREDIT_CARD',
                    ],
                ]
            );

            $payload = $response->json();
            $data = data_get($payload, 'data');

            if (!$response->successful() || !is_array($data) || empty($data['bookingId'])) {
                Log::warning('LiteAPI book request failed.', [
                    'status' => $response->status(),
                    'code' => data_get($payload, 'error.code'),
                    'message' => data_get($payload, 'error.message')
                        ?? data_get($payload, 'error.description'),
                ]);

                return $this->failure('The booking could not be confirmed. You have not been charged.');
            }

            return [
                'ok' => true,
                'error' => null,
                'data' => [
                    'booking_id' => $data['bookingId'],
                    'status' => $data['status'] ?? null,
                    'hotel_confirmation_code' => $data['hotelConfirmationCode'] ?? null,
                    'hotel_id' => data_get($data, 'hotel.hotelId'),
                    'hotel_name' => data_get($data, 'hotel.name'),
                    'check_in' => $data['checkin'] ?? null,
                    'check_out' => $data['checkout'] ?? null,
                    'price' => is_numeric($data['price'] ?? null) ? round((float) $data['price'], 2) : null,
                    'currency' => $data['currency'] ?? null,
                    'refundable' => ($data['tag'] ?? null) === 'RFN',
                    'last_free_cancellation' => $data['lastFreeCancellationDate'] ?? null,
                    'sandbox' => (bool) data_get($payload, 'sandbox', true),
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI book connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The booking service could not be reached. Please check My bookings before trying again.');
        }
    }

    /**
     * Cancel a confirmed booking with the supplier.
     *
     * This cannot be undone. A non-refundable booking is still
     * cancelled, but little or nothing is refunded.
     *
     * Returns ['ok' => bool, 'error' => ?string, 'data' => ?array].
     * The data holds the supplier status, any cancellation fee and
     * the refund amount.
     */
    public function cancel(string $bookingId): array
    {
        $bookingId = trim($bookingId);

        if (!$this->configured() || !preg_match('/^[A-Za-z0-9_-]+$/', $bookingId)) {
            return $this->failure('This booking could not be cancelled.');
        }

        try {
            $response = $this->client(40)->put(
                $this->bookUrl('/bookings/'.$bookingId).'?timeout=30'
            );

            $payload = $response->json();
            $data = data_get($payload, 'data');
            $status = strtoupper((string) data_get($data, 'status'));

            if ($response->status() !== 200 || !is_array($data) || !str_starts_with($status, 'CANCEL')) {
                Log::warning('LiteAPI cancel request failed.', [
                    'status' => $response->status(),
                    'booking_id' => $bookingId,
                    'code' => data_get($payload, 'error.code'),
                    'message' => data_get($payload, 'error.message')
                        ?? data_get($payload, 'error.description'),
                ]);

                return $this->failure('The booking could not be cancelled. It is still active. Please try again or contact support.');
            }

            return [
                'ok' => true,
                'error' => null,
                'data' => [
                    'booking_id' => $data['bookingId'] ?? $bookingId,
                    'status' => $status,
                    'cancellation_fee' => is_numeric($data['cancellation_fee'] ?? null)
                        ? round((float) $data['cancellation_fee'], 2)
                        : null,
                    'refund_amount' => is_numeric($data['refund_amount'] ?? null)
                        ? round((float) $data['refund_amount'], 2)
                        : null,
                    'currency' => $data['currency'] ?? null,
                    'sandbox' => (bool) data_get($payload, 'sandbox', $this->sandbox()),
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI cancel connection error.', [
                'booking_id' => $bookingId,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The booking service could not be reached, so the booking was not cancelled. Please try again.');
        }
    }

    /**
     * Whether the configured key is a sandbox (test) key.
     */
    public function sandbox(): bool
    {
        return str_starts_with((string) config('services.liteapi.api_key'), 'sand_');
    }

    /**
     * Build a failed result with a message that is safe to show.
     */
    protected function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'data' => null];
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
     * Build the full URL for a search or data API path.
     */
    protected function url(string $path): string
    {
        return rtrim((string) config('services.liteapi.base_url'), '/').$path;
    }

    /**
     * Build the full URL for a booking API path.
     */
    protected function bookUrl(string $path): string
    {
        return rtrim((string) config('services.liteapi.book_base_url'), '/').$path;
    }
}
