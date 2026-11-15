<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiteApiFlightService
{
    /**
     * Check whether a LiteAPI key is configured.
     */
    public function configured(): bool
    {
        return (bool) config('services.liteapi.api_key');
    }

    /**
     * Search airports by name, city or IATA code.
     */
    public function airports(string $query): array
    {
        $query = trim($query);

        if (!$this->configured() || mb_strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'liteapi_airports_'.md5(mb_strtolower($query));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client(12)->get($this->url('/data/flights/airports'), [
                'q' => $query,
            ]);

            if (!$response->successful()) {
                Log::warning('LiteAPI airports request failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $airports = collect($response->json('data') ?? [])
                ->flatMap(fn ($set) => data_get($set, 'airports', []))
                ->filter(fn ($airport) => !empty($airport['iata']) && !empty($airport['name']))
                ->map(fn ($airport) => [
                    'iata' => strtoupper($airport['iata']),
                    'name' => $airport['name'],
                    'city' => $airport['city'] ?? null,
                    'country' => $airport['country'] ?? null,
                    'label' => $airport['name'].' ('.strtoupper($airport['iata']).')'
                        .(!empty($airport['city']) ? ', '.$airport['city'] : ''),
                ])
                ->unique('iata')
                ->values()
                ->all();

            // Only keep successful, non-empty responses in the cache.
            if (count($airports) > 0) {
                Cache::put($cacheKey, $airports, now()->addHours(24));
            }

            return $airports;

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI airports connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search flights.
     *
     * Criteria:
     * [
     *     'origin' => 'LHR',              // IATA code, required
     *     'destination' => 'JFK',         // IATA code, required
     *     'depart_date' => '2026-12-14',  // required
     *     'return_date' => '2026-12-21',  // optional; makes it a return trip
     *     'adults' => 1,                  // optional, default 1 (age 12+)
     *     'children' => 0,                // optional (age 2 to 11)
     *     'infants' => 0,                 // optional (under 2)
     *     'cabin_class' => 'ECONOMY',     // optional
     *     'max_stops' => 0,               // optional; 0 means direct only
     *     'sort' => 'price',              // optional: price, duration, departure
     *     'currency' => 'GBP',            // optional
     * ]
     *
     * Returns a list of journeys in one standard format, each with its
     * cheapest fare. Fares expire after a few minutes, so results are
     * only kept for five minutes.
     */
    public function search(array $criteria): array
    {
        $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        $departDate = (string) ($criteria['depart_date'] ?? '');
        $returnDate = (string) ($criteria['return_date'] ?? '');

        if (
            !$this->configured()
            || !preg_match('/^[A-Z]{3}$/', $origin)
            || !preg_match('/^[A-Z]{3}$/', $destination)
            || $origin === $destination
            || strtotime($departDate) === false
        ) {
            return [];
        }

        $legs = [[
            'origin' => $origin,
            'destination' => $destination,
            'date' => date('Y-m-d', strtotime($departDate)),
            'direction' => 'OUTBOUND',
        ]];

        if ($returnDate !== '' && strtotime($returnDate) !== false) {
            $legs[] = [
                'origin' => $destination,
                'destination' => $origin,
                'date' => date('Y-m-d', strtotime($returnDate)),
                'direction' => 'INBOUND',
            ];
        }

        $filters = ['showCheapestOfferOnly' => true];

        if (isset($criteria['max_stops']) && is_numeric($criteria['max_stops'])) {
            $filters['maxStops'] = max(0, min((int) $criteria['max_stops'], 2));
        }

        $sort = in_array($criteria['sort'] ?? null, ['price', 'duration', 'departure'], true)
            ? $criteria['sort']
            : 'price';

        $body = [
            'legs' => $legs,
            'adults' => max(1, min((int) ($criteria['adults'] ?? 1), 9)),
            'children' => max(0, min((int) ($criteria['children'] ?? 0), 8)),
            'infants' => max(0, min((int) ($criteria['infants'] ?? 0), 4)),
            'currency' => strtoupper((string) ($criteria['currency'] ?? 'GBP')),
            'country' => 'GB',
            'filters' => $filters,
            'sort' => ['sortBy' => $sort, 'sortOrder' => 'asc'],
        ];

        $cabin = strtoupper((string) ($criteria['cabin_class'] ?? ''));

        if (in_array($cabin, ['ECONOMY', 'PREMIUM_ECONOMY', 'BUSINESS', 'FIRST'], true)) {
            $body['cabinClass'] = $cabin;
        }

        $cacheKey = 'liteapi_flights_'.md5(json_encode($body));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->client(75)->post($this->url('/flights/rates'), $body);

            $payload = $response->json();

            if (!$response->successful() || !is_array($payload) || isset($payload['error'])) {
                Log::warning('LiteAPI flight search failed.', [
                    'status' => $response->status(),
                    'code' => data_get($payload, 'error.code'),
                    'message' => data_get($payload, 'error.message')
                        ?? data_get($payload, 'error.description'),
                ]);

                return [];
            }

            $journeys = collect($payload['data'] ?? [])
                ->flatMap(fn ($set) => data_get($set, 'journeys', []))
                ->map(fn ($journey) => $this->journey($journey))
                ->filter()
                ->unique('key')
                ->values()
                ->all();

            if (count($journeys) > 0) {
                Cache::put($cacheKey, $journeys, now()->addMinutes(5));
            }

            return $journeys;

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI flight search connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Recheck a chosen fare before booking.
     *
     * Returns ['ok' => bool, 'error' => ?string, 'code' => mixed,
     * 'data' => ?array]. The data holds the current total and whether
     * the price changed since the search.
     */
    public function verify(string $offerId): array
    {
        if (!$this->configured() || trim($offerId) === '') {
            return $this->failure('This fare could not be checked.');
        }

        try {
            $response = $this->client(60)->post($this->url('/flights/verify'), [
                'offerId' => trim($offerId),
            ]);

            $payload = $response->json();
            $item = data_get($payload, 'data.0');
            $total = data_get($item, 'journey.pricing.display.total');

            if (!$response->successful() || !is_array($item) || !is_numeric($total)) {
                return $this->supplierFailure(
                    'verify',
                    $response->status(),
                    $payload,
                    'This fare is no longer available. Please search again and choose another flight.'
                );
            }

            return [
                'ok' => true,
                'error' => null,
                'code' => null,
                'data' => [
                    'price' => round((float) $total, 2),
                    'currency' => data_get($item, 'journey.pricing.display.currency'),
                    'price_changed' => (bool) data_get($item, 'changes.priceChanged', false),
                    'old_price' => data_get($item, 'changes.pricing.old.display.total'),
                    'fare_changed' => (bool) data_get($item, 'changes.fareChanged', false),
                    'expires_at' => data_get($item, 'journey.expiration'),
                    'refundable' => (bool) data_get($item, 'journey.terms.refundable', false),
                    'changeable' => (bool) data_get($item, 'journey.terms.changeable', false),
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI flight verify connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The flight service could not be reached. Please try again.');
        }
    }

    /**
     * Reserve a fare with the traveller details, ready to be booked.
     *
     * $contact: ['firstName', 'lastName', 'email', 'phoneCountryCode', 'phoneNumber']
     * $passengers: one entry per traveller, each with 'passengerType'
     *   (0 adult, 1 child, 2 infant), 'firstName', 'lastName', 'birthday'
     *   (YYYY-MM-DD), 'gender' (M, F or X) and 'nationality' (two-letter
     *   country code). Passport fields are optional.
     *
     * Returns ['ok' => bool, 'error' => ?string, 'code' => mixed,
     * 'data' => ?array]. The prebook_id in the data is needed by book().
     */
    public function prebook(string $offerId, array $contact, array $passengers, bool $usePaymentSdk = false): array
    {
        if (!$this->configured() || trim($offerId) === '' || count($passengers) === 0) {
            return $this->failure('This flight could not be reserved.');
        }

        try {
            $response = $this->client(75)->post($this->url('/flights/prebooks'), [
                'offerId' => trim($offerId),
                'usePaymentSdk' => $usePaymentSdk,
                'travelPurpose' => 'LEISURE',
                'contact' => $contact,
                'passengers' => array_values($passengers),
            ]);

            $payload = $response->json();
            $item = data_get($payload, 'data.0');

            if (!$response->successful() || !is_array($item) || empty($item['prebookId'])) {
                return $this->supplierFailure(
                    'prebook',
                    $response->status(),
                    $payload,
                    'This flight could not be reserved. Please check the traveller details or choose another flight.'
                );
            }

            return [
                'ok' => true,
                'error' => null,
                'code' => null,
                'data' => [
                    'prebook_id' => $item['prebookId'],
                    'price' => is_numeric($item['price'] ?? null) ? round((float) $item['price'], 2) : null,
                    'currency' => $item['currency'] ?? null,
                    'booking_ref' => data_get($item, 'booking.bookingRef'),
                    'payment_types' => (array) ($item['paymentTypes'] ?? []),
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI flight prebook connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The flight service could not be reached. Please try again.');
        }
    }

    /**
     * Confirm a reserved flight.
     *
     * Only sandbox keys are accepted. Sandbox bookings are simulated
     * and nothing is charged. Live payments are not set up yet, so a
     * production key is refused here.
     *
     * Returns ['ok' => bool, 'error' => ?string, 'code' => mixed,
     * 'data' => ?array].
     */
    public function book(string $prebookId): array
    {
        if (!$this->configured() || trim($prebookId) === '') {
            return $this->failure('This booking could not be started.');
        }

        if (!$this->sandbox()) {
            return $this->failure('Online payment is not available yet, so this flight was not booked.');
        }

        try {
            $response = $this->client(90)->post($this->url('/flights/bookings'), [
                'prebookId' => trim($prebookId),
                'payment' => [
                    'method' => 'ACC_CREDIT_CARD',
                ],
            ]);

            $payload = $response->json();
            $booking = data_get($payload, 'data.0.booking');

            if (!$response->successful() || !is_array($booking) || empty($booking['bookingId'])) {
                return $this->supplierFailure(
                    'book',
                    $response->status(),
                    $payload,
                    'The flight could not be booked. You have not been charged.'
                );
            }

            return [
                'ok' => true,
                'error' => null,
                'code' => null,
                'data' => [
                    'booking_id' => $booking['bookingId'],
                    'booking_ref' => $booking['bookingRef'] ?? null,
                    'status' => strtoupper((string) ($booking['status'] ?? '')),
                    'payment_status' => $booking['paymentStatus'] ?? null,
                    'pnrs' => collect(data_get($booking, 'order.reference.airlineBookings') ?? [])
                        ->map(fn ($airline) => data_get($airline, 'pnr') ?: data_get($airline, 'airlinePnr'))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'price' => is_numeric(data_get($booking, 'pricing.totalAmount'))
                        ? round((float) data_get($booking, 'pricing.totalAmount'), 2)
                        : null,
                    'currency' => data_get($booking, 'pricing.currency'),
                    'sandbox' => true,
                ],
            ];

        } catch (\Throwable $exception) {
            Log::warning('LiteAPI flight book connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('The flight service could not be reached. Please check My bookings before trying again.');
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
    protected function failure(string $message, mixed $code = null): array
    {
        return ['ok' => false, 'error' => $message, 'code' => $code, 'data' => null];
    }

    /**
     * Log a supplier error and build a failed result.
     */
    protected function supplierFailure(string $step, int $status, mixed $payload, string $message): array
    {
        $code = data_get($payload, 'error.code');

        Log::warning('LiteAPI flight '.$step.' failed.', [
            'status' => $status,
            'code' => $code,
            'key' => data_get($payload, 'error.key'),
            'message' => data_get($payload, 'error.message'),
            'description' => data_get($payload, 'error.description'),
        ]);

        // Code 43002 means the traveller details were rejected. The
        // supplier's explanation tells the guest what to correct.
        $description = data_get($payload, 'error.description');

        if ($code === 43002 && is_string($description) && trim($description) !== '') {
            $message = 'Please check the traveller details. '.trim($description);
        }

        return $this->failure($message, $code);
    }

    /**
     * Convert one supplier journey into the standard format.
     */
    protected function journey(array $journey): ?array
    {
        $offer = $journey['cheapestOffer'] ?? ($journey['offers'][0] ?? null);
        $total = data_get($offer, 'pricing.display.total');
        $segments = $journey['segments'] ?? [];

        if (!is_array($offer) || !is_numeric($total) || empty($offer['offerId']) || count($segments) === 0) {
            return null;
        }

        $legs = [];

        foreach (['OUTBOUND', 'INBOUND'] as $direction) {
            $parts = array_values(array_filter(
                $segments,
                fn ($segment) => strtoupper((string) ($segment['direction'] ?? 'OUTBOUND')) === $direction
            ));

            if (count($parts) === 0) {
                continue;
            }

            $legDuration = collect($journey['legDurations'] ?? [])
                ->first(fn ($leg) => strtoupper((string) data_get($leg, 'direction')) === $direction);

            $first = $parts[0];
            $last = $parts[count($parts) - 1];

            $legs[] = [
                'direction' => $direction,
                'origin_code' => $first['originCode'] ?? null,
                'origin_name' => $first['originName'] ?? null,
                'destination_code' => $last['destinationCode'] ?? null,
                'destination_name' => $last['destinationName'] ?? null,
                'departure' => $first['departureTime'] ?? null,
                'arrival' => $last['arrivalTime'] ?? null,
                'duration_minutes' => data_get($legDuration, 'duration.minutes'),
                'day_change' => (int) data_get($legDuration, 'dayChange', 0),
                // Changes of plane, plus any technical stops within a flight.
                'stops' => count($parts) - 1 + (int) array_sum(array_column($parts, 'stopCount')),
                'via' => array_values(array_filter(array_map(
                    fn ($segment) => $segment['destinationCode'] ?? null,
                    array_slice($parts, 0, -1)
                ))),
                'segments' => array_map(fn ($segment) => [
                    'origin_code' => $segment['originCode'] ?? null,
                    'origin_name' => $segment['originName'] ?? null,
                    'destination_code' => $segment['destinationCode'] ?? null,
                    'destination_name' => $segment['destinationName'] ?? null,
                    'departure' => $segment['departureTime'] ?? null,
                    'arrival' => $segment['arrivalTime'] ?? null,
                    'duration_minutes' => data_get($segment, 'duration.minutes'),
                    'airline_code' => data_get($segment, 'carrier.marketingCode'),
                    'airline_name' => data_get($segment, 'carrier.marketingName'),
                    'airline_logo' => data_get($segment, 'carrier.marketingLogo'),
                    'flight_number' => trim(
                        data_get($segment, 'carrier.marketingCode', '').' '.data_get($segment, 'flight.marketingNumber', '')
                    ),
                    'operated_by' => data_get($segment, 'carrier.operatingName'),
                ], $parts),
            ];
        }

        if (count($legs) === 0) {
            return null;
        }

        return [
            'key' => $journey['journeyKey'] ?? md5(json_encode($segments)),
            'offer_id' => $offer['offerId'],
            'expires_at' => $offer['expiration'] ?? null,
            'price' => round((float) $total, 2),
            'currency' => data_get($offer, 'pricing.display.currency'),
            'price_per_adult' => is_numeric(data_get($offer, 'pricing.display.perPassenger.adult.total'))
                ? round((float) data_get($offer, 'pricing.display.perPassenger.adult.total'), 2)
                : null,
            'duration_minutes' => data_get($journey, 'totalDuration.minutes'),
            'airlines' => collect($legs)
                ->flatMap(fn ($leg) => array_column($leg['segments'], 'airline_name'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'airline_logo' => $legs[0]['segments'][0]['airline_logo'] ?? null,
            'cabin' => data_get($offer, 'segmentFares.0.cabin'),
            'fare_family' => data_get($offer, 'fare.family'),
            'seats_remaining' => data_get($offer, 'fare.seatsRemaining'),
            'carry_on_bag' => (bool) data_get($offer, 'baggage.hasCarryOnBag', false),
            'checked_bag' => (bool) data_get($offer, 'baggage.hasCheckedBag', false),
            'refundable' => (bool) data_get($offer, 'terms.refundable', false),
            'changeable' => (bool) data_get($offer, 'terms.changeable', false),
            'legs' => $legs,
        ];
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
