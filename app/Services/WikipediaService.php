<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WikipediaService
{
    public function summary(string $title): ?array
    {
        $slug = str_replace(' ', '_', trim($title));
        return Cache::remember('wiki_summary_'.md5($slug), now()->addHours(12), function () use ($slug) {
            $response = Http::withHeaders(['User-Agent' => 'StaygoTravel/1.0 (destination guide)'])
                ->timeout(12)->get('https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($slug));
            if (!$response->successful()) return null;
            $data = $response->json();
            return [
                'title' => $data['title'] ?? $slug,
                'extract' => $data['extract'] ?? null,
                'url' => $data['content_urls']['desktop']['page'] ?? 'https://en.wikipedia.org/wiki/'.rawurlencode($slug),
                'thumbnail' => $data['thumbnail']['source'] ?? null,
            ];
        });
    }
}
