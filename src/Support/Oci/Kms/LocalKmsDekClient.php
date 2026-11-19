<?php

namespace App\Support\Oci\Kms;

use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Local KMS stand-in for PHPUnit and environments with ENCRYPTION_KMS_ENABLED=false.
 *
 * Wraps DEKs with Laravel Crypt (APP_KEY) instead of OCI KMS.
 */
class LocalKmsDekClient implements KmsDekClientInterface
{
    private const WRAP_PREFIX = 'local-dek:';

    public function generateDek(): OciKmsDekResult
    {
        $plaintextDek = random_bytes(32);
        $wrappedDek = self::WRAP_PREFIX.base64_encode(Crypt::encryptString(base64_encode($plaintextDek)));

        return OciKmsDekResult::success($plaintextDek, $wrappedDek);
    }

    public function decryptDek(string $wrappedDek): OciKmsDekResult
    {
        if (! str_starts_with($wrappedDek, self::WRAP_PREFIX)) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Configuration,
                'Wrapped DEK is not in local test format.',
            );
        }

        try {
            $encrypted = base64_decode(substr($wrappedDek, strlen(self::WRAP_PREFIX)), true);

            if ($encrypted === false) {
                throw new RuntimeException('Invalid wrapped DEK encoding.');
            }

            $plaintextDek = base64_decode(Crypt::decryptString($encrypted), true);

            if ($plaintextDek === false || strlen($plaintextDek) !== 32) {
                throw new RuntimeException('Invalid plaintext DEK length.');
            }
        } catch (\Throwable $exception) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Unknown,
                $exception->getMessage(),
            );
        }

        return OciKmsDekResult::success($plaintextDek, $wrappedDek);
    }
}
