<?php

namespace App\Support\Product\Http\Requests\Concerns;

trait ProductActivationRequestRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function stateFilterRules(): array
    {
        return [
            'state' => ['nullable', 'string', 'in:pending,active,suspended,expired'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function indexFilterRules(): array
    {
        return [
            'product_key' => ['nullable', 'string'],
            ...$this->stateFilterRules(),
            'user_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function optionalReasonRules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function requiredReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function approvePayloadRules(): array
    {
        return [
            'payload' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function statsFilterRules(): array
    {
        return [
            'product_key' => ['nullable', 'string'],
        ];
    }
}
