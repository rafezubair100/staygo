<?php

namespace App\Contracts;

interface PlaceProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Search destinations or places using normalized criteria.
     *
     * Example:
     * [
     *     'keyword' => 'London',
     *     'latitude' => 51.5072,
     *     'longitude' => -0.1276,
     * ]
     */
    public function search(array $criteria): array;
}
