<?php

namespace App\Support\CoreHub\Exceptions;

use RuntimeException;

/**
 * The hub could not be reached, or answered with a payload this consumer cannot
 * trust (bad signature, stale epoch, unexpected shape).
 *
 * Thrown, never swallowed inside an implementation — the caller decides whether
 * a stale local mirror is an acceptable answer for its surface (a profile read)
 * or not (a checkout). `reason` is a short, non-secret code for the log line:
 * `timeout`, `refused`, `signature`, `http_5xx`, `malformed`.
 */
class HubUnreachable extends RuntimeException
{
    public function __construct(
        public readonly string $reason = 'unreachable',
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : __('api/common.core_hub_unreachable'),
            0,
            $previous,
        );
    }

    public static function timeout(?\Throwable $previous = null): self
    {
        return new self('timeout', previous: $previous);
    }

    public static function refused(?\Throwable $previous = null): self
    {
        return new self('refused', previous: $previous);
    }

    public static function signature(): self
    {
        return new self('signature', __('api/common.core_hub_signature_invalid'));
    }

    public static function http(int $status): self
    {
        return new self('http_'.$status);
    }

    public static function malformed(): self
    {
        return new self('malformed', __('api/common.core_hub_malformed_payload'));
    }
}
