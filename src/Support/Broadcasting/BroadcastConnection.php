<?php

namespace App\Support\Broadcasting;

/**
 * Decides whether the web notification bell uses WebSocket push or HTTP polling.
 *
 * Realtime delivery requires `platform.notifications.delivery` = `realtime`
 * (Settings → Platform) AND a usable broadcast driver (Pusher / Socket-compatible).
 * Polling mode and misconfigured realtime always fall back to HTTP polling so the
 * bell keeps working without a websocket server.
 */
class BroadcastConnection
{
    /**
     * Whether the default broadcast connection has the credentials it needs.
     */
    public static function isConfigured(): bool
    {
        $connection = (string) config('broadcasting.default');

        if ($connection === '' || in_array($connection, ['null', 'log'], true)) {
            return false;
        }

        $config = config("broadcasting.connections.{$connection}");

        if (! is_array($config)) {
            return false;
        }

        return match ($config['driver'] ?? null) {
            'pusher', 'reverb' => filled($config['key'] ?? null)
                && filled($config['secret'] ?? null)
                && filled($config['app_id'] ?? null),
            default => false,
        };
    }

    /**
     * Realtime websocket delivery for the navbar bell.
     */
    public static function usesRealtimeNotifications(): bool
    {
        return setting_config('notifications.delivery') === 'realtime' && self::isConfigured();
    }

    /**
     * Client-safe Pusher / Echo options for the Blade bell (no secrets).
     * Requires realtime delivery mode AND configured credentials.
     *
     * @return array<string, mixed>|null
     */
    public static function clientConfig(): ?array
    {
        if (! self::usesRealtimeNotifications()) {
            return null;
        }

        return self::socketClientConfig();
    }

    /**
     * Client-safe Pusher / Echo options whenever a broadcast driver is configured.
     * Independent of `platform.notifications.delivery` so other domains (e.g. Archivd
     * live listing) can use websockets while the bell stays on polling.
     *
     * @return array<string, mixed>|null
     */
    public static function socketClientConfig(): ?array
    {
        if (! self::isConfigured()) {
            return null;
        }

        $connection = (string) config('broadcasting.default');
        $config = config("broadcasting.connections.{$connection}");

        if (! is_array($config)) {
            return null;
        }

        $options = is_array($config['options'] ?? null) ? $config['options'] : [];

        // Only pass a host override for self-hosted / Reverb sockets. Laravel's
        // default pusher options set host to `api-{cluster}.pusher.com` (the REST
        // API host). Echo treats any host as wsHost and then the browser never
        // reaches Pusher's real WebSocket endpoint — silent "always polling".
        $host = self::clientSocketHost($options['host'] ?? null);

        return [
            'key' => $config['key'],
            'cluster' => $options['cluster'] ?? null,
            'host' => $host,
            'port' => (int) ($options['port'] ?? 443),
            'scheme' => $options['scheme'] ?? 'https',
            // Always the versioned API endpoint: the framework's web-group twin
            // (`/broadcasting/auth`) is CSRF-guarded and not CORS-covered, so a
            // client sent there fails as an opaque browser CORS error.
            'authEndpoint' => BroadcastAuthEndpoint::url(),
        ];
    }

    /**
     * Socket host for the browser Echo client, or null for Pusher-hosted WS.
     */
    private static function clientSocketHost(mixed $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        // Default Laravel pusher REST host — must NOT be used as wsHost.
        if (preg_match('/^api-[a-z0-9-]+\.pusher\.com$/i', $host) === 1) {
            return null;
        }

        return $host;
    }
}
