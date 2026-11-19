<?php

namespace App\Http\Requests\Concerns;

trait InteractsWithListQueryPaging
{
    /**
     * Whether the query-mode client asked for a paged `{ items, pagination }` envelope.
     *
     * Absent paging params keep the legacy flat `data: T[]` body.
     */
    public function wantsQueryPaging(): bool
    {
        return $this->exists('page') || $this->exists('per_page') || $this->filled('search');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listQueryPagingRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
