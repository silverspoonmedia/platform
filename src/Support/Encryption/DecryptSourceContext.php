<?php

namespace App\Support\Encryption;

/**
 * Request-scoped stack of explicit decrypt trigger labels for invoice payload tracing.
 */
final class DecryptSourceContext
{
    private const INSTANCE = 'encryption.decrypt_source_context';

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function run(string $source, callable $callback, array $meta = []): mixed
    {
        self::push($source, $meta);

        try {
            return $callback();
        } finally {
            self::pop();
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function push(string $source, array $meta = []): void
    {
        $stack = self::stack();
        $stack[] = ['source' => $source, 'meta' => $meta];
        app()->instance(self::INSTANCE, $stack);
    }

    public static function pop(): void
    {
        $stack = self::stack();
        array_pop($stack);
        app()->instance(self::INSTANCE, $stack);
    }

    /**
     * @return list<array{source: string, meta: array<string, mixed>}>
     */
    public static function stack(): array
    {
        if (! app()->bound(self::INSTANCE)) {
            return [];
        }

        return app(self::INSTANCE);
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(
            static fn (array $entry): string => $entry['source'],
            self::stack(),
        );
    }
}
