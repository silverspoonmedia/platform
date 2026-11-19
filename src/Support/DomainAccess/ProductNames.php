<?php

namespace App\Support\DomainAccess;

use Illuminate\Support\Str;

/**
 * Naming helpers for product-domain access RBAC.
 *
 * A product domain key is StudlyCase (e.g. Archivd). We derive:
 * - human label:  "Archivd"
 * - beta role:    "Beta tester Archivd"
 * - access perm:  "access Archivd product"
 */
class ProductNames
{
    public static function label(string $productKey): string
    {
        return Str::headline($productKey);
    }

    public static function betaRole(string $productKey): string
    {
        return 'Beta tester '.self::label($productKey);
    }

    public static function accessPermission(string $productKey): string
    {
        return 'access '.Str::lower(self::label($productKey)).' product';
    }
}
