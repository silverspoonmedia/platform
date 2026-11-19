<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait InteractsWithListDisplayMode
{
    use InteractsWithListQueryPaging;

    /**
     * @return array<string, mixed>
     */
    protected function listDisplayModeRules(): array
    {
        return array_merge([
            'display_mode' => ['sometimes', 'nullable', 'string', Rule::in(['query'])],
        ], $this->listQueryPagingRules());
    }
}
