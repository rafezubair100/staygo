<?php

namespace App\Contracts;

interface PlaceProvider
{
    /**
     * Unique provider identifier.
     */
    public function name(): string;

    /**
     * Check whether provider access is available.
     */
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
