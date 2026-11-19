<?php

namespace App\Support\Product;

use App\Support\Product\Enums\ProductActivationType;
use RuntimeException;

/**
 * Static catalog of registered products and their activation type.
 *
 * Sourced from config/products.php. This is the runtime-access catalog,
 * independent of config/domains.php (which is module taxonomy / lifecycle).
 */
class ProductRegistry
{
    /**
     * @return array<string, array{
     *     activation_type: string,
     *     fulfillment_target: ?string,
     *     menu_routes?: list<string>
     * }>
     */
    public static function all(): array
    {
        $products = config('products');

        if (! is_array($products)) {
            return [];
        }

        return $products;
    }

    public static function has(string $productKey): bool
    {
        return array_key_exists($productKey, self::all());
    }

    public static function activationType(string $productKey): ProductActivationType
    {
        $config = self::all()[$productKey] ?? null;

        if ($config === null) {
            throw new RuntimeException("Unknown product key [{$productKey}].");
        }

        return ProductActivationType::from($config['activation_type']);
    }

    public static function isImplicit(string $productKey): bool
    {
        return self::activationType($productKey) === ProductActivationType::Implicit;
    }

    public static function isExplicit(string $productKey): bool
    {
        return self::activationType($productKey) === ProductActivationType::Explicit;
    }

    public static function fulfillmentTarget(string $productKey): ?string
    {
        $config = self::all()[$productKey] ?? null;

        return $config['fulfillment_target'] ?? null;
    }

    /**
     * Map a Service catalog fulfillment_target to a product key.
     */
    public static function productKeyByFulfillmentTarget(string $target): ?string
    {
        foreach (self::all() as $key => $config) {
            if (($config['fulfillment_target'] ?? null) === $target) {
                return $key;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function implicitProductKeys(): array
    {
        $keys = [];

        foreach (self::all() as $key => $config) {
            if (($config['activation_type'] ?? null) === ProductActivationType::Implicit->value) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return list<string> */
    public static function explicitProductKeys(): array
    {
        $keys = [];

        foreach (self::all() as $key => $config) {
            if (($config['activation_type'] ?? null) === ProductActivationType::Explicit->value) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Menu routes that should be hidden when the user's activation
     * for the given product is not Active.
     *
     * @return list<string>
     */
    public static function menuRoutesFor(string $productKey): array
    {
        $config = self::all()[$productKey] ?? null;

        if ($config === null) {
            return [];
        }

        $routes = $config['menu_routes'] ?? [];

        return is_array($routes) ? array_values($routes) : [];
    }

    /**
     * Reverse lookup: given a named menu route, return the product
     * key whose menu_routes list contains it, or null when the route
     * is not bound to any product. Used by MenuRepository visibility
     * filter to decide whether a menu node needs a product-activation
     * check.
     */
    public static function productKeyByMenuRoute(string $route): ?string
    {
        foreach (self::all() as $key => $config) {
            $routes = $config['menu_routes'] ?? [];

            if (is_array($routes) && in_array($route, $routes, true)) {
                return $key;
            }
        }

        return null;
    }
}
