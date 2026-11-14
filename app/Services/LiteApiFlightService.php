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
