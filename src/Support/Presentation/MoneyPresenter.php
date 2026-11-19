<?php

namespace App\Support\Presentation;

/**
 * Locale-aware money presentation for API Resources.
 *
 * Raw numeric amount + currency code stay the machine contract;
 * companions are display-only strings.
 */
final class MoneyPresenter
{
    private const DECIMALS = 2;

    public static function formatted(int|float|string|null $amount, ?string $currency): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            return null;
        }

        $number = self::formatNumber((float) $amount);
        $code = trim((string) ($currency ?? ''));

        if ($code === '') {
            return $number;
        }

        return $code.' '.$number;
    }

    private static function formatNumber(float $amount): string
    {
        $locale = app()->getLocale();

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, self::DECIMALS);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, self::DECIMALS);
            $formatted = $formatter->format($amount);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return match ($locale) {
            'id' => number_format($amount, self::DECIMALS, ',', '.'),
            'ja' => number_format($amount, self::DECIMALS, '.', ','),
            default => number_format($amount, self::DECIMALS, '.', ','),
        };
    }
}
