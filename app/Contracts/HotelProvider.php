<?php

namespace App\Contracts;

interface HotelProvider
{
    /**
     * Unique provider identifier.
     */
    public function name(): string;

    /**
     * Check whether provider credentials are configured.
     */
    public function isConfigured(): bool;

    /**
     * Search hotels using normalized search criteria.
     *
     * @param array $criteria
     * @return array
     */
    public function search(array $criteria): array;
}
