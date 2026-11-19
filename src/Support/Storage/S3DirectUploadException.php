<?php

namespace App\Support\Storage;

use RuntimeException;

/**
 * Transport-level failure for browser-direct S3 uploads.
 *
 * Domain services map {@see $reason} to translated BusinessRuleException messages.
 */
class S3DirectUploadException extends RuntimeException
{
    public const UNSUPPORTED_DISK = 'unsupported_disk';

    public const INCOMPLETE_PARTS = 'incomplete_parts';

    public const INVALID_SIZE = 'invalid_size';

    public const FILE_TOO_LARGE = 'file_too_large';

    public const INVALID_NAME = 'invalid_name';

    public const MIME_NOT_ALLOWED = 'mime_not_allowed';

    /** Upload proxy base_url set but token missing or disk has no target alias. */
    public const PROXY_MISCONFIGURED = 'proxy_misconfigured';

    /** Upstream S3-compatible API rejected create/complete/abort (e.g. NoSuchUpload). */
    public const UPSTREAM_FAILED = 'upstream_failed';

    public function __construct(
        public readonly string $reason,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
