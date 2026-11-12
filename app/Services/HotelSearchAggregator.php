<?php

namespace App\Services;

use App\Contracts\HotelProvider;
use Illuminate\Support\Facades\Log;

class HotelSearchAggregator
{
    /**
     * Search all registered hotel providers.
     */
    public function search(array $criteria): array
    {
        $results = [];
        $providers = [];

        // Retrieve all providers registered with Laravel.
        $registeredProviders = iterator_to_array(
            app()->tagged('hotel.providers')
        );

        // Fallback to the default hotel provider if no providers are tagged.
        if (count($registeredProviders) === 0) {
            $registeredProviders = [
                app(HotelProvider::class),
            ];
        }

        foreach ($registeredProviders as $provider) {

            // Ignore invalid provider registrations.
            if (!$provider instanceof HotelProvider) {
                continue;
            }

            $providerName = $provider->name();

            // Skip providers without API credentials.
            if (!$provider->isConfigured()) {
                $providers[$providerName] = 'not_configured';
                continue;
            }

            try {
                // Search this provider independently.
                $providerResults = $provider->search($criteria);

                $providers[$providerName] = 'searched';

                foreach ($providerResults as $hotel) {
                    $results[] = [
                        'provider' => $providerName,
                        'hotel' => $hotel,
                    ];
                }

            } catch (\Throwable $exception) {

                // A provider failure must not stop other providers.
                $providers[$providerName] = 'failed';

                Log::warning('Hotel provider search failed.', [
                    'provider' => $providerName,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'results' => $results,
            'providers' => $providers,
            'total' => count($results),
        ];
    }
}
