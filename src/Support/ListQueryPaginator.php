<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * In-memory search + page slice for query-mode list indexes.
 *
 * PHP may still load the catalog; the HTTP body is one page (default 10).
 * Users query-paging without `search` is SQL `LIMIT` (see list-display-modes.md);
 * other domains remain in-memory.
 */
final class ListQueryPaginator
{
    public const DEFAULT_PER_PAGE = 10;

    public const MAX_PER_PAGE = 100;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $filtered = self::filter($rows, $request);
        $perPage = self::perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        $total = $filtered->count();
        $slice = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice->all(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int, has_more: bool}
     */
    public static function meta(LengthAwarePaginatorContract $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function filter(Collection $rows, Request $request): Collection
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return $rows->values();
        }

        $needle = mb_strtolower($search);

        return $rows->filter(fn (mixed $row): bool => self::rowMatches($row, $needle))->values();
    }

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min(self::MAX_PER_PAGE, $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE));
    }

    private static function rowMatches(mixed $value, string $needle): bool
    {
        if (is_array($value)) {
            foreach ($value as $inner) {
                if (self::rowMatches($inner, $needle)) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return self::rowMatches((array) $value, $needle);
        }

        if ($value === null || is_bool($value)) {
            return false;
        }

        return str_contains(mb_strtolower((string) $value), $needle);
    }
}
