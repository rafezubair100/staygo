<?php

namespace App\Http\Controllers;

use App\Services\PlaceSearchAggregator;
use App\Services\WeatherService;
use App\Services\WikipediaService;

class DestinationController extends Controller
{
    /**
     * Show a destination guide: summary, weather and things to see.
     */
    public function show(
        string $city,
        WikipediaService $wiki,
        WeatherService $weather,
        PlaceSearchAggregator $places
    ) {
        // The address uses hyphens ("new-york"); services need spaces.
        $name = ucwords(str_replace('-', ' ', $city));

        $summary = $wiki->summary($name);

        // Names such as "New York" lead to a page listing several meanings,
        // which has no location. Look for the best matching place instead.
        if ($summary === null || empty($summary['coordinates'])) {
            $title = $wiki->findPlaceTitle($name);

            if ($title !== null) {
                $summary = $wiki->summary($title) ?? $summary;
            }
        }

        $center = $summary['coordinates'] ?? null;

        // Weather uses the coordinates, so it works for any place found.
        $conditions = $weather->current(
            $summary['title'] ?? $name,
            $center['latitude'] ?? null,
            $center['longitude'] ?? null
        );

        $criteria = [
            'keyword' => $summary['title'] ?? $name,
            'limit' => 12,
        ];

        if ($center !== null) {
            $criteria['latitude'] = $center['latitude'];
            $criteria['longitude'] = $center['longitude'];
        }

        // The plain "wikipedia" provider returns the destination itself,
        // which is already shown as the summary, so it is left out here.
        $attractions = collect($places->search($criteria)['results'])
            ->filter(fn ($result) => $result['provider'] !== 'wikipedia')
            ->pluck('place')
            ->filter(fn ($place) => !empty($place['name']))
            ->values()
            ->all();

        $markers = collect($attractions)
            ->filter(fn ($place) => is_numeric($place['latitude'] ?? null) && is_numeric($place['longitude'] ?? null))
            ->map(fn ($place) => [
                'name' => $place['name'],
                'lat' => (float) $place['latitude'],
                'lng' => (float) $place['longitude'],
            ])
            ->values()
            ->all();

        return view('destinations.show', compact(
            'city',
            'name',
            'summary',
            'conditions',
            'attractions',
            'markers',
            'center'
        ));
    }
}
