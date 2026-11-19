<?php

namespace App\Support\Presentation;

/**
 * Locale-aware FX rate presentation for API Resources.
 */
final class FxPresenter
{
    private const MAX_DECIMALS = 8;

    public static function formattedRate(int|float|string|null $rate): ?string
    {
        if ($rate === null || $rate === '' || ! is_numeric($rate)) {
            return null;
        }

        $value = (float) $rate;
        $locale = app()->getLocale();

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, self::MAX_DECIMALS);
            $formatted = $formatter->format($value);

            if ($formatted !== false) {
                return self::trimTrailingZeros($formatted);
            }
        }

        $formatted = match ($locale) {
            'id' => number_format($value, self::MAX_DECIMALS, ',', '.'),
            'ja' => number_format($value, self::MAX_DECIMALS, '.', ','),
            default => number_format($value, self::MAX_DECIMALS, '.', ','),
        };

        return self::trimTrailingZeros($formatted);
    }

    public static function label(int|float|string|null $rate, ?string $from, ?string $to): ?string
    {
        $formatted = self::formattedRate($rate);
        $fromCode = strtoupper(trim((string) ($from ?? '')));
        $toCode = strtoupper(trim((string) ($to ?? '')));

        if ($formatted === null || $fromCode === '' || $toCode === '') {
            return $formatted;
        }

        if ($fromCode === $toCode) {
            return '1 '.$fromCode.' = 1 '.$toCode;
        }

        return '1 '.$fromCode.' = '.$formatted.' '.$toCode;
    }

    private static function trimTrailingZeros(string $formatted): string
    {
        if (! str_contains($formatted, ',') && ! str_contains($formatted, '.')) {
            return $formatted;
        }

        $decimalSeparator = str_contains($formatted, ',') ? ',' : '.';
        [$whole, $fraction] = explode($decimalSeparator, $formatted, 2);
        $fraction = rtrim($fraction, '0');

        if ($fraction === '') {
            return $whole;
        }

        return $whole.$decimalSeparator.$fraction;
    }
}
