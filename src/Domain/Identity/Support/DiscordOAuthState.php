<?php

namespace App\Domain\Identity\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Signed OAuth state for the Discord connect flow.
 */
final class DiscordOAuthState
{
    /**
     * @return array{user_id: string, intent: string, return_to?: string}
     */
    public static function encode(
        string $userId,
        string $intent,
        ?string $returnTo = null,
    ): string {
        return Crypt::encrypt([
            'user_id' => $userId,
            'intent' => $intent,
            'return_to' => $returnTo,
            'exp' => now()->addMinutes(15)->timestamp,
        ]);
    }

    /**
     * @return array{user_id: string, intent: string, return_to?: string}
     */
    public static function decode(string $state): array
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = Crypt::decrypt($state);
        } catch (DecryptException) {
            throw new \InvalidArgumentException('Invalid OAuth state.');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < now()->timestamp) {
            throw new \InvalidArgumentException('OAuth state expired.');
        }

        $userId = (string) ($payload['user_id'] ?? '');
        $intent = (string) ($payload['intent'] ?? '');
        if ($userId === '' || $intent === '') {
            throw new \InvalidArgumentException('Invalid OAuth state payload.');
        }

        $returnTo = isset($payload['return_to']) ? (string) $payload['return_to'] : null;

        return [
            'user_id' => $userId,
            'intent' => $intent,
            'return_to' => $returnTo !== '' ? $returnTo : null,
        ];
    }
}
