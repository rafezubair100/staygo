<?php

namespace App\Services;

use App\Contracts\PlaceProvider;

class WikipediaNearbyPlaceProvider implements PlaceProvider
{
    /**
     * Words in a Wikipedia short description that identify a place
     * a visitor can go to.
     */
    protected const PLACE_WORDS = [
        'museum', 'gallery', 'cathedral', 'church', 'basilica', 'chapel',
        'abbey', 'monastery', 'temple', 'mosque', 'synagogue', 'shrine',
        'pagoda', 'palace', 'castle', 'château', 'chateau', 'fortress',
        'fort', 'citadel', 'tower', 'skyscraper', 'bridge', 'arch', 'gate',
        'monument', 'memorial', 'statue', 'sculpture', 'fountain', 'obelisk',
        'mausoleum', 'tomb', 'cemetery', 'ossuary', 'catacombs', 'park',
        'garden', 'gardens', 'zoo', 'aquarium', 'square', 'plaza', 'piazza',
        'street', 'avenue', 'boulevard', 'promenade',
        'neighbourhood', 'neighborhood', 'quarter', 'hill', 'mountain',
        'island', 'beach', 'lake', 'river', 'canal', 'harbour', 'harbor',
        'pier', 'market', 'bazaar', 'theatre', 'theater', 'opera', 'stadium',
        'arena', 'library', 'landmark', 'building', 'buildings', 'ruins',
        'amphitheatre', 'amphitheater', 'observatory', 'lighthouse',
        'waterfall', 'attraction', 'heritage',
    ];

    /**
     * Words that mark an event, organisation or historical topic,
     * even when a place word is also present.
     */
    protected const EXCLUDED_WORDS = [
        'battle', 'attack', 'shooting', 'bombing', 'crash', 'death',
        'murder', 'massacre', 'riot', 'protest', 'war', 'revolution',
        'siege', 'election', 'tournament', 'championship', 'ceremony',
        'festival', 'club', 'team', 'company', 'organisation',
        'organization', 'institution', 'government', 'parliament',
        'republic', 'empire', 'kingdom', 'council', 'party', 'timeline',
        'history', 'event', 'list',
    ];

    public function __construct(
        protected WikipediaService $wikipedia
    ) {
    }

    public function name(): string
    {
        return 'wikipedia_nearby';
    }

    /**
     * Wikipedia's public API does not require an API key.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * Search well-known places to visit near a destination.
     *
     * Uses these entries of the normalized criteria:
     * [
     *     'keyword' => 'London',      // city name; used to look up coordinates when none are given
     *     'latitude' => 51.5072,      // optional
     *     'longitude' => -0.1276,     // optional
     *     'limit' => 12,              // optional, 1 to 50
     * ]
     */
    public function search(array $criteria): array
    {
        $city = $criteria['keyword'] ?? null;
        $city = is_string($city) && trim($city) !== ''
            ? trim($city)
            : null;

        $latitude = $criteria['latitude'] ?? null;
        $longitude = $criteria['longitude'] ?? null;

        // Titles that describe the destination itself, not an attraction.
        $excludedTitles = $city !== null ? [mb_strtolower($city)] : [];

        // Without coordinates, look them up from the destination's summary.
        if (!$this->validCoordinates($latitude, $longitude)) {
            if ($city === null) {
                return [];
            }

            $summary = $this->wikipedia->summary($city);

            $latitude = $summary['coordinates']['latitude'] ?? null;
            $longitude = $summary['coordinates']['longitude'] ?? null;

            if (!$this->validCoordinates($latitude, $longitude)) {
                return [];
            }

            if (!empty($summary['title'])) {
                $excludedTitles[] = mb_strtolower($summary['title']);
            }
        }

        $limit = $criteria['limit'] ?? 12;
        $limit = is_numeric($limit) ? max(1, min((int) $limit, 50)) : 12;

        // Request more than needed because many results are filtered out.
        $places = $this->wikipedia->nearby(
            (float) $latitude,
            (float) $longitude,
            8000,
            50
        );

        $results = [];

        foreach ($places as $place) {
            $title = $place['title'] ?? null;

            if (!is_string($title) || $title === '') {
                continue;
            }

            // Skip the destination's own article.
            if (in_array(mb_strtolower($title), $excludedTitles, true)) {
                continue;
            }

            // Skip entries without a photo.
            if (empty($place['thumbnail'])) {
                continue;
            }

            // Keep only articles described as a place to visit.
            if (!$this->describesPlace($place['description'] ?? null)) {
                continue;
            }

            $results[] = [
                'name' => $title,
                'description' => $place['description'] ?? null,
                'url' => $place['url'] ?? null,
                'image' => $place['thumbnail'],
                'city' => $city,
                'latitude' => $place['latitude'] ?? null,
                'longitude' => $place['longitude'] ?? null,
                'distance' => $place['distance'] ?? null,
                'source' => 'wikipedia',
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Decide from the short description whether an article is a
     * place to visit, as opposed to an event or an organisation.
     */
    protected function describesPlace(mixed $description): bool
    {
        if (!is_string($description) || trim($description) === '') {
            return false;
        }

        $description = mb_strtolower($description);

        // Descriptions that start with a year describe events.
        if (preg_match('/^\d{3,4}\b/u', $description)) {
            return false;
        }

        $words = preg_split('/[^\p{L}]+/u', $description, -1, PREG_SPLIT_NO_EMPTY);

        if (count(array_intersect($words, self::EXCLUDED_WORDS)) > 0) {
            return false;
        }

        return count(array_intersect($words, self::PLACE_WORDS)) > 0;
    }

    /**
     * Check that both values are numeric and within valid ranges.
     */
    protected function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }
}