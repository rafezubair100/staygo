<?php

namespace App\Services;

use App\Contracts\HotelProvider;

class LiteApiHotelProvider implements HotelProvider
{
    public function __construct(
        protected LiteApiService $liteApi
    ) {
    }

    public function name(): string
    {
        return 'liteapi';
    }

    public function isConfigured(): bool
    {
        return $this->liteApi->configured();
    }

    /**
     * Search hotels with live prices anywhere in the world.
     *
     * Uses these entries of the normalized criteria:
     * [
     *     'destination' => 'Tokyo',       // free text; used when no place_id is given
     *     'place_id' => '...',            // optional LiteAPI place ID
     *     'check_in' => '2026-12-01',     // required
     *     'check_out' => '2026-12-03',    // required
     *     'adults' => 2,                  // optional, default 2
     *     'rooms' => 1,                   // optional, default 1
     *     'children_ages' => [5, 9],      // optional
     *     'currency' => 'GBP',            // optional
     *     'stars' => 4,                   // optional
     *     'sort' => 'price_asc',          // optional: price_asc or price_desc
     *     'limit' => 20,                  // optional, 1 to 50
     * ]
     *
     * Each result is one hotel with its cheapest available offer.
     */
    public function search(array $criteria): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $checkIn = $criteria['check_in'] ?? null;
        $checkOut = $criteria['check_out'] ?? null;

        if (!is_string($checkIn) || !is_string($checkOut) || $checkIn === '' || $checkOut === '') {
            return [];
        }

        $placeId = $this->resolvePlaceId($criteria);

        if ($placeId === null) {
            return [];
        }

        $limit = $criteria['limit'] ?? 20;
        $limit = is_numeric($limit) ? max(1, min((int) $limit, 50)) : 20;

        $options = [
            'limit' => $limit,
            'maxRatesPerHotel' => 1,
        ];

        if (!empty($criteria['currency']) && is_string($criteria['currency'])) {
            $options['currency'] = strtoupper($criteria['currency']);
        }

        if (!empty($criteria['stars']) && is_numeric($criteria['stars'])) {
            $options['starRating'] = [(int) $criteria['stars']];
        }

        $sort = $criteria['sort'] ?? null;

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $options['sort'] = [[
                'field' => 'price',
                'direction' => $sort === 'price_asc' ? 'ascending' : 'descending',
            ]];
        }

        $response = $this->liteApi->rates(
            ['placeId' => $placeId],
            $checkIn,
            $checkOut,
            $this->occupancies($criteria),
            $options
        );

        $nights = $this->nights($checkIn, $checkOut);

        // Hotel details arrive in a separate list, keyed here by hotel ID.
        $details = [];

        foreach ($response['hotels'] as $hotel) {
            if (!empty($hotel['id'])) {
                $details[$hotel['id']] = $hotel;
            }
        }

        $results = [];

        foreach ($response['rates'] as $entry) {
            $hotelId = $entry['hotelId'] ?? null;
            $offer = $entry['roomTypes'][0] ?? null;

            if (!$hotelId || !is_array($offer)) {
                continue;
            }

            $amount = data_get($offer, 'offerRetailRate.amount');

            if (!is_numeric($amount)) {
                continue;
            }

            $hotel = $details[$hotelId] ?? [];
            $rate = $offer['rates'][0] ?? [];

            $results[] = [
                'provider' => 'liteapi',
                'id' => $hotelId,
                'name' => $hotel['name'] ?? 'Hotel',
                'address' => $hotel['address'] ?? null,
                'city' => $hotel['city_name'] ?? null,
                'country_code' => $hotel['country_code'] ?? null,
                'latitude' => $hotel['latitude'] ?? null,
                'longitude' => $hotel['longitude'] ?? null,
                'stars' => $hotel['stars'] ?? null,
                'rating' => $hotel['rating'] ?? null,
                'review_count' => $hotel['review_count'] ?? null,
                'image' => $hotel['main_photo'] ?? null,
                'thumbnail' => $hotel['thumbnail'] ?? null,
                'price' => round((float) $amount, 2),
                'currency' => data_get($offer, 'offerRetailRate.currency'),
                'nights' => $nights,
                'price_per_night' => $nights > 0 ? round((float) $amount / $nights, 2) : null,
                'room_name' => $rate['name'] ?? null,
                'board' => $rate['boardName'] ?? null,
                'refundable' => data_get($rate, 'cancellationPolicies.refundableTag') === 'RFN',
                'offer_id' => $offer['offerId'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Use the given place ID, or look one up from the destination text.
     */
    protected function resolvePlaceId(array $criteria): ?string
    {
        $placeId = $criteria['place_id'] ?? null;

        if (is_string($placeId) && trim($placeId) !== '') {
            return trim($placeId);
        }

        $destination = $criteria['destination'] ?? null;

        if (!is_string($destination) || trim($destination) === '') {
            return null;
        }

        $places = $this->liteApi->places($destination);

        return $places[0]['place_id'] ?? null;
    }

    /**
     * Build one occupancy per room, sharing the adults between rooms.
     * Children are placed in the first room.
     */
    protected function occupancies(array $criteria): array
    {
        $adults = $criteria['adults'] ?? 2;
        $adults = is_numeric($adults) ? max(1, (int) $adults) : 2;

        $rooms = $criteria['rooms'] ?? 1;
        $rooms = is_numeric($rooms) ? max(1, min((int) $rooms, $adults)) : 1;

        $ages = [];

        foreach ((array) ($criteria['children_ages'] ?? []) as $age) {
            if (is_numeric($age) && (int) $age >= 0 && (int) $age <= 17) {
                $ages[] = (int) $age;
            }
        }

        $occupancies = [];

        for ($room = 0; $room < $rooms; $room++) {
            $occupancy = [
                'adults' => intdiv($adults, $rooms) + ($room < $adults % $rooms ? 1 : 0),
            ];

            if ($room === 0 && count($ages) > 0) {
                $occupancy['children'] = $ages;
            }

            $occupancies[] = $occupancy;
        }

        return $occupancies;
    }

    /**
     * Number of nights between two dates, or 0 when they are invalid.
     */
    protected function nights(string $checkIn, string $checkOut): int
    {
        $start = strtotime($checkIn);
        $end = strtotime($checkOut);

        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return (int) round(($end - $start) / 86400);
    }
}