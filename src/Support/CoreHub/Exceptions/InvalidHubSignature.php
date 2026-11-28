<?php

namespace App\Support\CoreHub\Exceptions;

use RuntimeException;

/**
 * A pushed payload arrived without a valid signature, or for a consumer this
 * install does not answer to.
 *
 * Deliberately distinct from {@see HubUnreachable}: that one is the *client* side
 * saying "I could not read the hub", this one is the *server* side refusing a
 * request. The middleware renders it as a 401 without leaking which check failed.
 */
class InvalidHubSignature extends RuntimeException
{
    public function __construct(string $reason = 'signature')
    {
        parent::__construct(__('api/common.core_hub_signature_invalid'));

        $this->reason = $reason;
    }

    public readonly string $reason;
}
