<?php

namespace App\Support\Encryption;

use RuntimeException;

class BlindIndex
{
    public static function make(string $value): string
    {
        $pepper = (string) config('encryption.blind_index_pepper');

        if ($pepper === '') {
            throw new RuntimeException('ENCRYPTION_BLIND_INDEX_PEPPER is not configured.');
        }

        $normalized = trim(mb_strtolower($value));

        return hash_hmac('sha256', $normalized, $pepper);
    }
}
