<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikipediaService
{
    protected const USER_AGENT = 'StaygoTravel/1.0 (destination guide)';

    /**
     * Fetch the Wikipedia summary for a page title.
     *
     * Returns title, extract, url, thumbnail and, when Wikipedia
     * knows them, the coordinates of the place.
     */
    public function summary(string $title): ?array
    {
        $slug = str_replace(' ', '_', trim($title));

        return Cache::remember('wiki_summary_v2_'.md5($slug), now()->addHours(12), function () use ($slug) {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(12)
                ->get('https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($slug));

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            $latitude = data_get($data, 'coordinates.lat');
            $longitude = data_get($data, 'coordinates.lon');

            return [
                'title' => $data['title'] ?? $slug,
                'extract' => $data['extract'] ?? null,
                'url' => $data['content_urls']['desktop']['page'] ?? 'https://en.wikipedia.org/wiki/'.rawurlencode($slug),
                'thumbnail' => $data['thumbnail']['source'] ?? null,
                'coordinates' => is_numeric($latitude) && is_numeric($longitude)
                    ? ['latitude' => (float) $latitude, 'longitude' => (float) $longitude]
                    : null,
            ];
        });
    }

    /**
     * Find Wikipedia articles about places near the given coordinates.
     *
     * Results are sorted by distance. Failed requests are not cached.
     */
    public function nearby(
        float $latitude,
        float $longitude,
        int $radius = 10000,
        int $limit = 20
    ): array {
        // Limits enforced by the Wikipedia geosearch API.
        $radius = max(10, min($radius, 10000));
        $limit = max(1, min($limit, 50));

        $point = round($latitude, 4).'|'.round($longitude, 4);
        $cacheKey = 'wiki_nearby_'.md5($point.'|'.$radius.'|'.$limit);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(15)
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action' => 'query',
                    'format' => 'json',
                    'formatversion' => 2,
                    'generator' => 'geosearch',
                    'ggscoord' => $point,
                    'ggsradius' => $radius,
                    'ggslimit' => $limit,
                    'prop' => 'coordinates|pageimages|description|info',
                    'inprop' => 'url',
                    'piprop' => 'thumbnail',
                    'pithumbsize' => 400,
                    'pilimit' => $limit,
                    'colimit' => $limit,
                    'codistancefrompoint' => $point,
                ]);

            if (!$response->successful()) {
                Log::warning('Wikipedia nearby request failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $places = collect($response->json('query.pages') ?? [])
                ->map(fn ($page) => [
                    'title' => data_get($page, 'title'),
                    'description' => data_get($page, 'description'),
                    'url' => data_get($page, 'fullurl'),
                    'thumbnail' => data_get($page, 'thumbnail.source'),
                    'latitude' => data_get($page, 'coordinates.0.lat'),
                    'longitude' => data_get($page, 'coordinates.0.lon'),
                    'distance' => data_get($page, 'coordinates.0.dist'),
                ])
                ->filter(fn ($place) => !empty($place['title']))
                ->sortBy(fn ($place) => $place['distance'] ?? PHP_FLOAT_MAX)
                ->values()
                ->all();

            Cache::put($cacheKey, $places, now()->addHours(12));

            return $places;

        } catch (\Throwable $exception) {
            Log::warning('Wikipedia nearby connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}