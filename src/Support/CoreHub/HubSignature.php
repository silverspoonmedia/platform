<?php

namespace App\Support\CoreHub;

/**
 * Canonical serialization and HMAC both directions agree on.
 *
 * One place computes the string that is signed and the header the signature is
 * carried in, so the hub's signer and a consumer's verifier cannot drift by a
 * field. The signed text is `{epoch}.{method}.{path}.{sha256(body)}` — the epoch
 * defeats a replay of a captured request, the body hash means the payload cannot
 * be altered after signing, and the method+path bind it to one endpoint.
 *
 * The algorithm is HMAC-SHA256 and the key is per-consumer: a secret that leaks
 * from one consumer cannot forge a push to another.
 */
final class HubSignature
{
    public const HEADER = 'X-Core-Hub-Signature';

    public const TIMESTAMP_HEADER = 'X-Core-Hub-Timestamp';

    public const CONSUMER_HEADER = 'X-Core-Hub-Consumer';

    /**
     * How far a timestamp may be from now before the request is refused.
     */
    public const TOLERANCE_SECONDS = 300;

    public static function payload(int $timestamp, string $method, string $path, string $body): string
    {
        return $timestamp.'.'.strtoupper($method).'.'.$path.'.'.hash('sha256', $body);
    }

    public static function sign(string $secret, int $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', self::payload($timestamp, $method, $path, $body), $secret);
    }

    public static function verify(
        string $secret,
        int $timestamp,
        string $method,
        string $path,
        string $body,
        string $signature,
    ): bool {
        if ($secret === '' || $signature === '') {
            return false;
        }

        if (abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(
            self::sign($secret, $timestamp, $method, $path, $body),
            strtolower($signature),
        );
    }
}
