<?php

namespace App\Support\Oci\Kms;

enum OciKmsFailureCategory: string
{
    case Configuration = 'configuration';
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case NotFound = 'not_found';
    case Network = 'network';
    case Unknown = 'unknown';

    public function exitCode(): int
    {
        return match ($this) {
            self::Configuration => 1,
            self::Authentication => 2,
            self::Authorization => 3,
            self::NotFound => 5,
            self::Network => 4,
            self::Unknown => 5,
        };
    }
}
