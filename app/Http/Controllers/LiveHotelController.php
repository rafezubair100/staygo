<?php

namespace App\Http\Controllers;

use App\Services\LiteApiService;
use Illuminate\Http\Request;

class LiveHotelController extends Controller
{
    /**
     * Show one live hotel with its available rooms and prices.
     */
    public function show(Request $request, string $hotelId, LiteApiService $liteApi)
    {
        $data = $request->validate([
            'check_in' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:12'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        // Default to a two-night stay one week from today.
        $checkIn = date('Y-m-d', strtotime($data['check_in'] ?? '+7 days'));
        $checkOut = !empty($data['check_out'])
            ? date('Y-m-d', strtotime($data['check_out']))
            : date('Y-m-d', strtotime($checkIn.' +2 days'));

        $adults = (int) ($data['adults'] ?? 2);
        $roomCount = min((int) ($data['rooms'] ?? 1), $adults);
        $nights = max(1, (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400));

        $hotel = $liteApi->hotel($hotelId);

        abort_if($hotel === null, 404);

        $rates = $liteApi->rates(
            ['hotelIds' => [$hotelId]],
            $checkIn,
            $checkOut,
            $this->occupancies($adults, $roomCount),
            ['roomMapping' => true]
        );

        $rooms = $this->groupOffers(
            $hotel,
            $rates['rates'][0]['roomTypes'] ?? [],
            $nights
        );

        return view('hotels.live', [
            'hotel' => $hotel,
            'rooms' => $rooms,
            'nights' => $nights,
            'criteria' => [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => $adults,
                'rooms' => $roomCount,
            ],
        ]);
    }

    /**
     * Group the priced offers of a hotel by room, cheapest first.
     *
     * Each room carries its details (photos, size, beds, amenities)
     * from the hotel data, and a list of offers that differ by board
     * and cancellation terms.
     */
    public function groupOffers(array $hotel, array $roomTypes, int $nights): array
    {
        $groups = [];

        foreach ($roomTypes as $roomType) {
            $rate = $roomType['rates'][0] ?? null;
            $amount = data_get($roomType, 'offerRetailRate.amount');
            $offerId = $roomType['offerId'] ?? null;

            if (!is_array($rate) || !is_numeric($amount) || !$offerId) {
                continue;
            }

            $roomId = data_get($rate, 'mappedRoomId') ?? data_get($roomType, 'mappedRoomId');
            $details = $roomId !== null ? ($hotel['rooms'][(string) $roomId] ?? null) : null;
            $rateName = trim((string) ($rate['name'] ?? 'Room'));

            $key = $details !== null
                ? 'room_'.$roomId
                : 'name_'.md5(mb_strtolower($rateName));

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => $details['name'] ?? $rateName,
                    'description' => $details['description'] ?? null,
                    'size' => $details['size'] ?? null,
                    'max_occupancy' => $details['max_occupancy'] ?? ($rate['maxOccupancy'] ?? null),
                    'beds' => $details['beds'] ?? [],
                    'amenities' => array_slice($details['amenities'] ?? [], 0, 8),
                    'photos' => array_slice($details['photos'] ?? [], 0, 5),
                    'offers' => [],
                ];
            }

            $groups[$key]['offers'][] = [
                'offer_id' => $offerId,
                'rate_name' => $rateName,
                'board' => $rate['boardName'] ?? null,
                'refundable' => data_get($rate, 'cancellationPolicies.refundableTag') === 'RFN',
                'cancel_by' => data_get($rate, 'cancellationPolicies.cancelPolicyInfos.0.cancelTime'),
                'price' => round((float) $amount, 2),
                'currency' => data_get($roomType, 'offerRetailRate.currency'),
                'price_per_night' => round((float) $amount / max(1, $nights), 2),
            ];
        }

        foreach ($groups as &$group) {
            usort($group['offers'], fn ($a, $b) => $a['price'] <=> $b['price']);
            $group['from_price'] = $group['offers'][0]['price'];
        }
        unset($group);

        usort($groups, fn ($a, $b) => $a['from_price'] <=> $b['from_price']);

        return $groups;
    }

    /**
     * Build one occupancy per room, sharing the adults between rooms.
     */
    protected function occupancies(int $adults, int $rooms): array
    {
        $adults = max(1, $adults);
        $rooms = max(1, min($rooms, $adults));

        $occupancies = [];

        for ($room = 0; $room < $rooms; $room++) {
            $occupancies[] = [
                'adults' => intdiv($adults, $rooms) + ($room < $adults % $rooms ? 1 : 0),
            ];
        }

        return $occupancies;
    }
}