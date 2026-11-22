<?php

namespace App\Support;

/**
 * Typed catalog of environment variables declared in .env.example.
 * Used for template quoting validation and auto-generated secret metadata.
 */
final class EnvironmentVariableCatalog
{
    public const TYPE_STRING = 'string';

    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_NULL = 'null';

    /** @var list<string> */
    private const AUTO_GENERATED = [
        'APP_KEY',
        'JWT_SECRET',
        'ENCRYPTION_BLIND_INDEX_PEPPER',
    ];

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'APP_NAME' => self::TYPE_STRING,
            'APP_ENV' => self::TYPE_STRING,
            'APP_KEY' => self::TYPE_STRING,
            'APP_PREVIOUS_KEYS' => self::TYPE_STRING,
            'APP_DEBUG' => self::TYPE_BOOL,
            'APP_URL' => self::TYPE_STRING,
            'SPA_URL' => self::TYPE_STRING,
            'CORS_EXTRA_ORIGINS' => self::TYPE_STRING,
            'APP_LOCALE' => self::TYPE_STRING,
            'APP_FALLBACK_LOCALE' => self::TYPE_STRING,
            'APP_FAKER_LOCALE' => self::TYPE_STRING,
            'APP_MAINTENANCE_DRIVER' => self::TYPE_STRING,
            'BCRYPT_ROUNDS' => self::TYPE_INT,
            'OCI_PROFILE' => self::TYPE_STRING,
            'OCI_KMS_KEY_ID' => self::TYPE_STRING,
            'OCI_KMS_CRYPTO_ENDPOINT' => self::TYPE_STRING,
            'OCI_HTTP_TIMEOUT' => self::TYPE_INT,
            'ENCRYPTION_KMS_ENABLED' => self::TYPE_BOOL,
            'ENCRYPTION_DECRYPTED_CACHE_MAX_TTL' => self::TYPE_INT,
            'ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS' => self::TYPE_BOOL,
            'ENCRYPTION_AUDIT_DECRYPTS' => self::TYPE_STRING,
            'ENCRYPTION_BLIND_INDEX_PEPPER' => self::TYPE_STRING,
            'ENCRYPTION_SESSION_ENABLED' => self::TYPE_BOOL,
            'WEB_LAYOUT' => self::TYPE_STRING,
            'JWT_ALGO' => self::TYPE_STRING,
            'JWT_SECRET' => self::TYPE_STRING,
            'AUTH_GUARD' => self::TYPE_STRING,
            'LOG_CHANNEL' => self::TYPE_STRING,
            'LOG_STACK' => self::TYPE_STRING,
            'LOG_DEPRECATIONS_CHANNEL' => self::TYPE_NULL,
            'LOG_LEVEL' => self::TYPE_STRING,
            'DB_CONNECTION' => self::TYPE_STRING,
            'DB_HOST' => self::TYPE_STRING,
            'DB_PORT' => self::TYPE_INT,
            'DB_DATABASE' => self::TYPE_STRING,
            'DB_USERNAME' => self::TYPE_STRING,
            'DB_PASSWORD' => self::TYPE_STRING,
            'ALLOW_DESTRUCTIVE_DB' => self::TYPE_BOOL,
            'SESSION_DRIVER' => self::TYPE_STRING,
            'SESSION_LIFETIME' => self::TYPE_INT,
            'SESSION_ENCRYPT' => self::TYPE_BOOL,
            'SESSION_PATH' => self::TYPE_STRING,
            'SESSION_DOMAIN' => self::TYPE_NULL,
            'SESSION_SECURE_COOKIE' => self::TYPE_BOOL,
            'BROADCAST_CONNECTION' => self::TYPE_STRING,
            'FILESYSTEM_DISK' => self::TYPE_STRING,
            'QUEUE_CONNECTION' => self::TYPE_STRING,
            'DB_QUEUE_RETRY_AFTER' => self::TYPE_INT,
            'CACHE_STORE' => self::TYPE_STRING,
            'MEMCACHED_HOST' => self::TYPE_STRING,
            'REDIS_CLIENT' => self::TYPE_STRING,
            'REDIS_HOST' => self::TYPE_STRING,
            'REDIS_PASSWORD' => self::TYPE_NULL,
            'REDIS_PORT' => self::TYPE_INT,
            'MAIL_MAILER' => self::TYPE_STRING,
            'MAIL_SCHEME' => self::TYPE_NULL,
            'MAIL_HOST' => self::TYPE_STRING,
            'MAIL_PORT' => self::TYPE_INT,
            'MAIL_USERNAME' => self::TYPE_NULL,
            'MAIL_PASSWORD' => self::TYPE_NULL,
            'HCAPTCHA_SECRET' => self::TYPE_STRING,
            'HCAPTCHA_SITEKEY' => self::TYPE_STRING,
            'PUSHER_APP_ID' => self::TYPE_STRING,
            'PUSHER_APP_KEY' => self::TYPE_STRING,
            'PUSHER_APP_SECRET' => self::TYPE_STRING,
            'PUSHER_PORT' => self::TYPE_STRING,
            'PUSHER_SCHEME' => self::TYPE_STRING,
            'PUSHER_APP_CLUSTER' => self::TYPE_STRING,
            'PUSHER_HOST' => self::TYPE_STRING,
            'FRANKFURTER_BASE_URL' => self::TYPE_STRING,
            'MIDTRANS_SERVER_KEY' => self::TYPE_STRING,
            'MIDTRANS_CLIENT_KEY' => self::TYPE_STRING,
            'MIDTRANS_IS_PRODUCTION' => self::TYPE_BOOL,
            'MIDTRANS_NOTIFY_URL' => self::TYPE_STRING,
            'IPAYMU_VA_KEY' => self::TYPE_STRING,
            'IPAYMU_API_KEY' => self::TYPE_STRING,
            'IPAYMU_IS_PRODUCTION' => self::TYPE_BOOL,
            'IPAYMU_NOTIFY_URL' => self::TYPE_STRING,
            'DISCORD_CLIENT_ID' => self::TYPE_STRING,
            'DISCORD_CLIENT_SECRET' => self::TYPE_STRING,
            'DISCORD_REDIRECT_URI' => self::TYPE_STRING,
        ];
    }

    /**
     * Every known key: the kernel's own plus whatever a product published.
     *
     * Product env keys (a storage disk, an upload proxy, a queue name) are product
     * knowledge, so the product registers them from its provider
     * (`config(['env-catalog.types' => [...]])`) and the kernel stays product-free.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [...self::types(), ...(array) config('env-catalog.types', [])];
    }

    public static function type(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }

    public static function isAutoGenerated(string $name): bool
    {
        return in_array($name, self::AUTO_GENERATED, true);
    }

    public static function valueMatchesType(string $type, string $rawValue): bool
    {
        return match ($type) {
            self::TYPE_STRING => self::isStringValue($rawValue),
            self::TYPE_BOOL => $rawValue === 'true' || $rawValue === 'false',
            self::TYPE_INT => preg_match('/^-?\d+$/', $rawValue) === 1,
            self::TYPE_NULL => $rawValue === 'null',
            default => false,
        };
    }

    /**
     * Re-emit a parsed assignment value using catalog type rules.
     * Unknown types (orphan keys) keep bool/int/null literals; otherwise quote as string.
     */
    public static function formatRawValue(?string $type, string $rawValue): string
    {
        $semantic = self::semanticValue($rawValue);

        return match ($type) {
            self::TYPE_STRING => self::quoteString($semantic),
            self::TYPE_BOOL => self::formatBool($semantic, $rawValue),
            self::TYPE_INT => self::formatInt($semantic, $rawValue),
            self::TYPE_NULL => self::formatNull($semantic),
            default => self::formatUnknown($rawValue, $semantic),
        };
    }

    /**
     * Strip surrounding double quotes and unescape \" and \\.
     */
    public static function semanticValue(string $rawValue): string
    {
        if (! self::isQuotedString($rawValue)) {
            return $rawValue;
        }

        return self::unescapeQuotedInner(substr($rawValue, 1, -1));
    }

    private static function unescapeQuotedInner(string $inner): string
    {
        $result = '';
        $length = strlen($inner);

        for ($index = 0; $index < $length; $index++) {
            if ($inner[$index] === '\\' && $index + 1 < $length) {
                $next = $inner[$index + 1];

                if ($next === '\\' || $next === '"') {
                    $result .= $next;
                    $index++;

                    continue;
                }
            }

            $result .= $inner[$index];
        }

        return $result;
    }

    private static function quoteString(string $semantic): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $semantic).'"';
    }

    private static function formatBool(string $semantic, string $rawValue): string
    {
        $normalized = strtolower($semantic);

        if ($normalized === 'true' || $normalized === 'false') {
            return $normalized;
        }

        return $rawValue;
    }

    private static function formatInt(string $semantic, string $rawValue): string
    {
        if (preg_match('/^-?\d+$/', $semantic) === 1) {
            return $semantic;
        }

        return $rawValue;
    }

    private static function formatNull(string $semantic): string
    {
        if ($semantic === '' || strtolower($semantic) === 'null') {
            return 'null';
        }

        return self::quoteString($semantic);
    }

    private static function formatUnknown(string $rawValue, string $semantic): string
    {
        if ($rawValue === 'true' || $rawValue === 'false') {
            return $rawValue;
        }

        if (preg_match('/^-?\d+$/', $rawValue) === 1) {
            return $rawValue;
        }

        if ($rawValue === 'null') {
            return 'null';
        }

        return self::quoteString($semantic);
    }

    private static function isStringValue(string $rawValue): bool
    {
        return self::isQuotedString($rawValue);
    }

    private static function isQuotedString(string $rawValue): bool
    {
        if (! str_starts_with($rawValue, '"')) {
            return false;
        }

        $length = strlen($rawValue);

        if ($length < 2 || $rawValue[$length - 1] !== '"') {
            return false;
        }

        for ($index = 1; $index < $length - 1; $index++) {
            if ($rawValue[$index] === '\\') {
                $index++;

                continue;
            }

            if ($rawValue[$index] === '"') {
                return false;
            }
        }

        return true;
    }
}
