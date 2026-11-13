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
     * Find Wikipedia articles located near the given coordinates.
     *
     * Results are ordered by Wikipedia's own relevance ranking, so
     * well-known articles come first. The list is unfiltered and can
     * include events and organisations as well as places.
     * Failed requests are not cached.
     */
    public function nearby(
        float $latitude,
        float $longitude,
        int $radius = 8000,
        int $limit = 50
    ): array {
        // The search works in whole kilometres.
        $radiusKm = max(1, min((int) round($radius / 1000), 50));
        $limit = max(1, min($limit, 50));

        $point = round($latitude, 4).'|'.round($longitude, 4);
        $cacheKey = 'wiki_nearby_v2_'.md5($point.'|'.$radiusKm.'|'.$limit);

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
                    'generator' => 'search',
                    'gsrsearch' => 'nearcoord:'.$radiusKm.'km,'.round($latitude, 4).','.round($longitude, 4),
                    'gsrnamespace' => 0,
                    'gsrlimit' => $limit,
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
                ->sortBy(fn ($page) => data_get($page, 'index', PHP_INT_MAX))
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
                ->values()
                ->all();

            // Only keep successful, non-empty responses in the cache.
            if (count($places) > 0) {
                Cache::put($cacheKey, $places, now()->addHours(12));
            }

            return $places;

        } catch (\Throwable $exception) {
            Log::warning('Wikipedia nearby connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}