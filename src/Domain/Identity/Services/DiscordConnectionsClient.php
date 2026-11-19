<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Discord user connections API — full connection snapshot for identity.
 */
class DiscordConnectionsClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchConnections(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->get('https://discord.com/api/users/@me/connections');
        } catch (RequestException $e) {
            throw new IdentityOAuthException(__('api/identity.oauth.connections_failed'), 502, $e);
        }

        if (! $response->successful()) {
            throw new IdentityOAuthException(__('api/identity.oauth.connections_failed'), 502);
        }

        /** @var list<array<string, mixed>> $raw */
        $raw = $response->json() ?? [];

        return $this->normalize($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    public function normalize(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            $id = (string) ($item['id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }

            $row = [
                'type' => $type,
                'id' => $id,
                'name' => (string) ($item['name'] ?? ''),
                'verified' => (bool) ($item['verified'] ?? false),
            ];

            if (array_key_exists('revoked', $item)) {
                $row['revoked'] = (bool) $item['revoked'];
            }
            if (array_key_exists('friend_sync', $item)) {
                $row['friend_sync'] = (bool) $item['friend_sync'];
            }
            if (array_key_exists('show_activity', $item)) {
                $row['show_activity'] = (bool) $item['show_activity'];
            }
            if (array_key_exists('two_way_link', $item)) {
                $row['two_way_link'] = (bool) $item['two_way_link'];
            }
            if (array_key_exists('visibility', $item) && is_int($item['visibility'])) {
                $row['visibility'] = $item['visibility'];
            }
            if (isset($item['integrations']) && is_array($item['integrations'])) {
                $row['integrations'] = $item['integrations'];
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
