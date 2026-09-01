<?php

namespace App\Domain\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Search engine abstraction — business logic must not depend on a concrete engine.
 * Drivers: sql (default) | meilisearch. Config: SEARCH_DRIVER.
 */
interface SearchProviderInterface
{
    /** @param array<string, mixed> $filters */
    public function searchServices(string $query, array $filters, int $perPage = 12): LengthAwarePaginator;

    /** Suggest matching keywords/categories for autocomplete. */
    public function suggest(string $query, int $limit = 8): array;
}
