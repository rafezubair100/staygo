<?php

namespace App\Services;

use App\Contracts\PlaceProvider;
use Illuminate\Support\Facades\Log;

class PlaceSearchAggregator
{
    /**
     * Search all registered place providers using normalized criteria.
     *
     * Example:
     * [
     *     'keyword' => 'London',
     *     'latitude' => 51.5072,
     *     'longitude' => -0.1276,
     * ]
     */
    public function search(array $criteria): array
    {
        $results = [];
        $providers = [];

        // Retrieve all providers registered with Laravel.
        $registeredProviders = iterator_to_array(
            app()->tagged('place.providers')
        );

        // Return an empty response if no providers are registered.
        if (count($registeredProviders) === 0) {
            return [
                'results' => [],
                'providers' => [],
                'total' => 0,
            ];
        }

        foreach ($registeredProviders as $provider) {

            // Ensure the provider follows the required interface.
            if (!$provider instanceof PlaceProvider) {
                continue;
            }

            $providerName = $provider->name();

            // Skip providers without API credentials.
            if (!$provider->isConfigured()) {
                $providers[$providerName] = 'not_configured';
                continue;
            }

            try {
                // Search the current provider.
                $providerResults = $provider->search($criteria);

                $providers[$providerName] = 'searched';

                foreach ($providerResults as $place) {
                    $results[] = [
                        'provider' => $providerName,
                        'place' => $place,
                    ];
                }

            } catch (\Throwable $exception) {

                // Continue even if an individual provider fails.
                $providers[$providerName] = 'failed';

                Log::warning('Place provider search failed.', [
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