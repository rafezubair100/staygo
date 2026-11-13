<?php

namespace App\Services;

use App\Contracts\PlaceProvider;

class WikipediaPlaceProvider implements PlaceProvider
{
    public function __construct(
        protected WikipediaService $wikipedia
    ) {
    }

    /**
     * Return the provider name.
     */
    public function name(): string
    {
        return 'wikipedia';
    }

    /**
     * Wikipedia's public summary API does not require an API key.
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * Search for a destination using Wikipedia's summary API.
     *
     * Uses the 'keyword' entry of the normalized criteria, for example:
     * ['keyword' => 'London']
     */
    public function search(array $criteria): array
    {
        $keyword = $criteria['keyword'] ?? '';

        if (!is_string($keyword)) {
            return [];
        }

        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $summary = $this->wikipedia->summary($keyword);

        if (!$summary || empty($summary['extract'])) {
            return [];
        }

        return [
            [
                'name' => $summary['title'],
                'description' => $summary['extract'],
                'url' => $summary['url'],
                'image' => $summary['thumbnail'],
                'source' => 'wikipedia',
            ],
        ];
    }
}