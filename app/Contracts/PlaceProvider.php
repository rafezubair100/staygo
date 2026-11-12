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
     * Search destinations or places.
     *
     * @param string $keyword
     * @return array
     */
    public function search(string $keyword): array;
}
