<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ListQueryPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait RespondsWithListDisplayMode
{
    /**
     * Query-mode list body: legacy flat `T[]` unless page/per_page/search are present,
     * then `{ items, pagination }` (same shape as the inference usage-event lists).
     *
     * @param  callable(mixed): array<int, array<string, mixed>>  $resolveRows
     */
    protected function respondToQueryList(
        object $request,
        mixed $list,
        callable $resolveRows,
        string $message,
    ): JsonResponse {
        $rows = Collection::make($resolveRows($list))->values();

        if (! $this->requestWantsQueryPaging($request)) {
            return $this->successResponse($rows->all(), $message);
        }

        /** @var Request $pagingRequest */
        $pagingRequest = $request instanceof Request ? $request : request();
        $page = ListQueryPaginator::paginate($rows, $pagingRequest);

        return $this->successResponse([
            'items' => array_values($page->items()),
            'pagination' => ListQueryPaginator::meta($page),
        ], $message);
    }

    private function requestWantsQueryPaging(object $request): bool
    {
        if (method_exists($request, 'wantsQueryPaging')) {
            return (bool) $request->wantsQueryPaging();
        }

        if ($request instanceof Request) {
            return $request->exists('page') || $request->exists('per_page') || $request->filled('search');
        }

        return false;
    }
}
