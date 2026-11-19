<?php

namespace App\Support\Oci\Kms;

readonly class OciKmsConnectionResult
{
    public function __construct(
        public bool $successful,
        public ?OciKmsFailureCategory $category = null,
        public ?string $message = null,
        public ?string $ociCode = null,
        public ?int $httpStatus = null,
    ) {}

    public static function success(): self
    {
        return new self(successful: true);
    }

    public static function failure(
        OciKmsFailureCategory $category,
        string $message,
        ?string $ociCode = null,
        ?int $httpStatus = null,
    ): self {
        return new self(
            successful: false,
            category: $category,
            message: $message,
            ociCode: $ociCode,
            httpStatus: $httpStatus,
        );
    }
}
