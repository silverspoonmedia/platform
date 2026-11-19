<?php

namespace App\Support\Presentation;

/**
 * Human-readable byte sizes for API Resources.
 *
 * Raw int bytes stay the machine contract; companions are display-only.
 */
final class BytePresenter
{
    public static function human(int|float|string|null $bytes): ?string
    {
        if ($bytes === null || $bytes === '') {
            return null;
        }

        if (! is_numeric($bytes)) {
            return null;
        }

        $value = (int) $bytes;

        if ($value < 0) {
            $value = 0;
        }

        if ($value === 0) {
            return '0 B';
        }

        if ($value < 1024) {
            return $value.' B';
        }

        if ($value < 1024 * 1024) {
            return number_format($value / 1024, 1).' KB';
        }

        if ($value < 1024 * 1024 * 1024) {
            return number_format($value / (1024 * 1024), 1).' MB';
        }

        return number_format($value / (1024 * 1024 * 1024), 1).' GB';
    }
}
