<?php

namespace App\Support\ListActions;

use App\Support\DataTable\ActionButton;
use InvalidArgumentException;

/**
 * Query-mode row action for SPA lists. Not {@see ActionButton}.
 *
 * Exactly one destination: SPA nav (`route` + `params`), public `href`, or mutate `method` + `path`.
 */
final readonly class QueryRowAction
{
    /**
     * @param  array<string, string|int>  $params
     * @param  array<string, string|int>  $query
     * @param  array{title: string, text: string}|null  $confirm
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $variant,
        public ?string $route = null,
        public array $params = [],
        public array $query = [],
        public ?string $href = null,
        public ?string $target = null,
        public ?string $method = null,
        public ?string $path = null,
        public ?array $confirm = null,
    ) {
        $kinds = (int) ($this->route !== null)
            + (int) ($this->href !== null)
            + (int) ($this->method !== null);

        if ($kinds !== 1) {
            throw new InvalidArgumentException('QueryRowAction must be exactly one of nav, href, or mutate.');
        }

        if ($this->method !== null && ($this->path === null || $this->path === '')) {
            throw new InvalidArgumentException('Mutate QueryRowAction requires path.');
        }
    }

    /**
     * @param  array<string, string|int>  $params
     * @param  array<string, string|int>  $query
     */
    public static function nav(
        string $key,
        string $label,
        string $icon,
        string $variant,
        string $route,
        array $params = [],
        array $query = [],
    ): self {
        return new self(
            key: $key,
            label: $label,
            icon: $icon,
            variant: $variant,
            route: $route,
            params: $params,
            query: $query,
        );
    }

    public static function href(
        string $key,
        string $label,
        string $icon,
        string $variant,
        string $href,
        string $target = '_blank',
    ): self {
        return new self(
            key: $key,
            label: $label,
            icon: $icon,
            variant: $variant,
            href: $href,
            target: $target,
        );
    }

    /**
     * @param  array{title: string, text: string}|null  $confirm
     */
    public static function mutate(
        string $key,
        string $label,
        string $icon,
        string $variant,
        string $method,
        string $path,
        ?array $confirm = null,
    ): self {
        return new self(
            key: $key,
            label: $label,
            icon: $icon,
            variant: $variant,
            method: $method,
            path: $path,
            confirm: $confirm,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
        ];

        if ($this->route !== null) {
            $payload['route'] = $this->route;
            if ($this->params !== []) {
                $payload['params'] = $this->params;
            }
            if ($this->query !== []) {
                $payload['query'] = $this->query;
            }

            return $payload;
        }

        if ($this->href !== null) {
            $payload['href'] = $this->href;
            if ($this->target !== null) {
                $payload['target'] = $this->target;
            }

            return $payload;
        }

        $payload['method'] = strtoupper((string) $this->method);
        $payload['path'] = $this->path;
        if ($this->confirm !== null) {
            $payload['confirm'] = $this->confirm;
        }

        return $payload;
    }
}
