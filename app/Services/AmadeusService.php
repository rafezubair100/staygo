<?php

namespace App\Services;

use App\Contracts\HotelProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AmadeusService implements HotelProvider
{
    public function name(): string
    {
        return 'amadeus';
    }

    public function isConfigured(): bool
    {
        return (bool) (
            config('services.amadeus.client_id') &&
            config('services.amadeus.client_secret')
        );
    }

    // Retained for compatibility with the existing controller.
    public function configured(): bool
    {
        return $this->isConfigured();
    }

    protected function token(): ?string
    {
        $id = config('services.amadeus.client_id');
        $secret = config('services.amadeus.client_secret');

        if (!$id || !$secret) {
            return null;
        }

        return Cache::remember(
            'amadeus_access_token',
            now()->addMinutes(25),
            function () use ($id, $secret) {
                $response = Http::asForm()
                    ->timeout(15)
                    ->post(
                        rtrim(config('services.amadeus.base_url'), '/') .
                        '/v1/security/oauth2/token',
                        [
                            'grant_type' => 'client_credentials',
                            'client_id' => $id,
                            'client_secret' => $secret,
                        ]
                    );

                $response->throw();

                return $response->json('access_token');
            }
        );
    }

    protected function get(string $path, array $query = []): array
    {
        $token = $this->token();

        if (!$token) {
            return [];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get(
                rtrim(config('services.amadeus.base_url'), '/') . $path,
                $query
            );

        if (!$response->successful()) {
            report(new RuntimeException(
                'Amadeus API returned ' .
                $response->status() . ': ' . $response->body()
            ));

            return [];
        }

        return $response->json() ?? [];
    }

    public function destinations(string $keyword): array
    {
        return $this->get('/v1/reference-data/locations', [
            'subType' => 'CITY',
            'keyword' => $keyword,
            'page[limit]' => 8,
        ])['data'] ?? [];
    }

    public function searchHotels(
        string $cityCode,
        string $checkIn,
        string $checkOut,
        int $adults = 2,
        int $rooms = 1
    ): array {
        $hotelData = $this->get(
            '/v1/reference-data/locations/hotels/by-city',
            [
                'cityCode' => strtoupper($cityCode),
                'radius' => 20,
                'radiusUnit' => 'KM',
                'hotelSource' => 'ALL',
            ]
        );

        $ids = collect($hotelData['data'] ?? [])
            ->pluck('hotelId')
            ->filter()
            ->take(20)
            ->implode(',');

        if (!$ids) {
            return [];
        }

        return $this->get('/v3/shopping/hotel-offers', [
            'hotelIds' => $ids,
            'adults' => max(1, $adults),
            'checkInDate' => $checkIn,
            'checkOutDate' => $checkOut,
            'roomQuantity' => max(1, $rooms),
            'currency' => 'GBP',
        ])['data'] ?? [];
    }

    /**
     * Search through the common HotelProvider interface.
     */
    public function search(array $criteria): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $cityCode = $criteria['city_code'] ?? null;
        $checkIn = $criteria['check_in'] ?? null;
        $checkOut = $criteria['check_out'] ?? null;

        if (!$cityCode || !$checkIn || !$checkOut) {
            return [];
        }

        return $this->searchHotels(
            $cityCode,
            $checkIn,
            $checkOut,
            (int) ($criteria['adults'] ?? 2),
            (int) ($criteria['rooms'] ?? 1)
        );
    }
}
