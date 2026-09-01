<?php

namespace App\Domain\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Meilisearch-backed provider. Activated with SEARCH_DRIVER=meilisearch.
 * Index is refreshed on service save (observer) — graceful failure keeps SQL path usable.
 */
class MeilisearchSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private readonly string $host,
        private readonly string $key,
    ) {
    }

    public static function fromConfig(): ?self
    {
        $host = rtrim((string) config('services.meilisearch.host', ''), '/');

        return $host !== '' ? new self($host, (string) config('services.meilisearch.key', '')) : null;
    }

    public function searchServices(string $query, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->key])
                ->timeout(5)
                ->post("{$this->host}/indexes/services/search", [
                    'q' => $query,
                    'limit' => $perPage,
                    'filter' => $this->buildFilter($filters),
                    'sort' => [$this->sortField($filters)],
                ])
                ->throw()
                ->json();

            $ids = collect($response['hits'] ?? [])->pluck('id')->all();

            return Service::query()
                ->active()
                ->with('category:id,name,slug,icon', 'partner:id,display_name,slug,avatar_path,rating_avg,verification_state')
                ->whereIn('id', $ids)
                ->orderByRaw('FIELD(id, '.implode(',', $ids ?: [0]).')')
                ->paginate($perPage);
        } catch (RuntimeException) {
            // graceful degradation: fall back to SQL provider
            return app(SqlSearchProvider::class)->searchServices($query, $filters, $perPage);
        }
    }

    private function buildFilter(array $filters): string
    {
        $parts = ['status = active'];
        if (! empty($filters['category'])) {
            $parts[] = 'category_slug = "'.addslashes($filters['category']).'"';
        }
        if (! empty($filters['emergency'])) {
            $parts[] = 'emergency_capable = true';
        }

        return implode(' AND ', $parts);
    }

    private function sortField(array $filters): string
    {
        return match ($filters['sort'] ?? null) {
            'price_asc' => 'base_price:asc',
            'price_desc' => 'base_price:desc',
            'rating' => 'rating_avg:desc',
            default => 'created_at:desc',
        };
    }

    public function suggest(string $query, int $limit = 8): array
    {
        return app(SqlSearchProvider::class)->suggest($query, $limit);
    }
}
