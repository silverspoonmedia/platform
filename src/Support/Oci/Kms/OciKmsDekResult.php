<?php

namespace App\Support\Oci\Kms;

readonly class OciKmsDekResult
{
    public function __construct(
        public bool $successful,
        public ?string $plaintextDek = null,
        public ?string $wrappedDek = null,
        public ?string $message = null,
        public ?OciKmsFailureCategory $category = null,
        public ?string $ociCode = null,
        public ?int $httpStatus = null,
    ) {}

    public static function success(string $plaintextDek, string $wrappedDek): self
    {
        return new self(
            successful: true,
            plaintextDek: $plaintextDek,
            wrappedDek: $wrappedDek,
        );
    }

    public static function failure(
        OciKmsFailureCategory $category,
        string $message,
        ?string $ociCode = null,
        ?int $httpStatus = null,
    ): self {
        return new self(
            successful: false,
            message: $message,
            category: $category,
            ociCode: $ociCode,
            httpStatus: $httpStatus,
        );
    }
}
