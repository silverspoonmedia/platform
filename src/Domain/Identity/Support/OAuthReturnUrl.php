<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Exceptions\IdentityOAuthException;

/**
 * Allowlisted post-OAuth browser return. Origins: APP_URL and SPA_URL.
 * Paths must stay under /apps/.
 */
final class OAuthReturnUrl
{
    public static function fromSpaPath(string $path): string
    {
        $normalized = self::normalizePath($path);
        $origin = rtrim((string) config('services.spa.url'), '/');
        if ($origin === '') {
            throw new IdentityOAuthException(__('api/identity.oauth.spa_url_missing'), 503);
        }

        $sanitized = self::sanitize($origin.$normalized);
        if ($sanitized === null) {
            throw new IdentityOAuthException(__('api/identity.oauth.return_path_invalid'), 422);
        }

        return $sanitized;
    }

    public static function sanitize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (! str_starts_with($path, '/apps/') || str_contains($path, '..') || str_contains($path, '\\')) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : self::defaultPort($scheme);
        if (! self::originAllowed($scheme, $host, $port)) {
            return null;
        }

        $origin = $scheme.'://'.$host.self::portSuffix($scheme, $port);

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $origin.$path.$query;
    }

    public static function withDiscordStatus(string $url, string $status): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['discord'] = $status;

        $scheme = strtolower((string) $parts['scheme']);
        $port = isset($parts['port']) ? (int) $parts['port'] : self::defaultPort($scheme);
        $origin = $scheme.'://'.$parts['host'].self::portSuffix($scheme, $port);
        $path = $parts['path'] ?? '';

        return $origin.$path.'?'.http_build_query($query);
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || ! str_starts_with($path, '/apps/') || str_contains($path, '://') || str_contains($path, '\\') || str_contains($path, '..')) {
            throw new IdentityOAuthException(__('api/identity.oauth.return_path_invalid'), 422);
        }

        $cut = strpos($path, '?');
        $pathname = $cut === false ? $path : substr($path, 0, $cut);
        $query = $cut === false ? '' : substr($path, $cut);

        if (str_contains($pathname, '//')) {
            throw new IdentityOAuthException(__('api/identity.oauth.return_path_invalid'), 422);
        }

        return $pathname.$query;
    }

    private static function originAllowed(string $scheme, string $host, int $port): bool
    {
        foreach (self::allowedOrigins() as $origin) {
            $parts = parse_url($origin);
            if (! is_array($parts) || ! isset($parts['host'])) {
                continue;
            }

            $allowedScheme = strtolower((string) ($parts['scheme'] ?? 'https'));
            $allowedHost = strtolower((string) $parts['host']);
            $allowedPort = isset($parts['port']) ? (int) $parts['port'] : self::defaultPort($allowedScheme);

            if ($allowedScheme === $scheme && $allowedHost === $host && $allowedPort === $port) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function allowedOrigins(): array
    {
        $origins = [];
        foreach ([config('app.url'), config('services.spa.url')] as $raw) {
            $trimmed = rtrim((string) $raw, '/');
            if ($trimmed !== '') {
                $origins[] = $trimmed;
            }
        }

        return $origins;
    }

    private static function defaultPort(string $scheme): int
    {
        return $scheme === 'http' ? 80 : 443;
    }

    private static function portSuffix(string $scheme, int $port): string
    {
        if ($port === self::defaultPort($scheme)) {
            return '';
        }

        return ':'.$port;
    }
}
