<?php

namespace App\Support\Oci;

/**
 * Resolves the OCI credential folder for the active profile.
 *
 * The active `.env` decides which folder is read — production uses
 * `.oci/production/`, every other profile (`local`, `staging`, `testing`) uses
 * `.oci/testing/`. Nothing is copied into a shared `.oci/config` anymore, so a
 * profile switch moves the credentials and the vault pair together.
 */
class OciConfigPath
{
    public const PRODUCTION_ENV = 'production';

    public const PRODUCTION_DIRECTORY = 'production';

    public const DEFAULT_DIRECTORY = 'testing';

    public static function directory(?string $appEnv, ?string $basePath = null): string
    {
        $basePath ??= base_path();

        return rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.'.oci'.DIRECTORY_SEPARATOR.self::directoryName($appEnv);
    }

    public static function configFile(?string $appEnv, ?string $basePath = null): string
    {
        return self::directory($appEnv, $basePath).DIRECTORY_SEPARATOR.'config';
    }

    public static function directoryName(?string $appEnv): string
    {
        return $appEnv === self::PRODUCTION_ENV
            ? self::PRODUCTION_DIRECTORY
            : self::DEFAULT_DIRECTORY;
    }
}
