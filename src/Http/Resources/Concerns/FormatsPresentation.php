<?php

namespace App\Http\Resources\Concerns;

use App\Support\Presentation\BytePresenter;
use App\Support\Presentation\DatePresenter;
use App\Support\Presentation\MoneyPresenter;
use Carbon\CarbonInterface;

/**
 * Dual-field presentation helpers for Eloquent API Resources.
 *
 * Raw machine values stay under the base key; display companions are
 * `{key}_formatted` / `{key}_human` (dates also get absolute + relative).
 */
trait FormatsPresentation
{
    /**
     * @return array<string, string|null>
     */
    protected function dateFields(string $key, CarbonInterface|string|null $value): array
    {
        [$iso, $formatted, $human] = DatePresenter::pack($value);

        return [
            $key => $iso,
            $key.'_formatted' => $formatted,
            $key.'_human' => $human,
        ];
    }

    /**
     * @return array<string, float|int|string|null>
     */
    protected function moneyFields(string $key, int|float|string|null $amount, ?string $currency, bool $asFloat = false): array
    {
        $raw = $amount;

        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            $raw = $asFloat ? (float) $amount : $amount;
        } elseif ($amount === '' || $amount === null) {
            $raw = null;
        }

        return [
            $key => $raw,
            $key.'_formatted' => MoneyPresenter::formatted(
                $amount !== null && $amount !== '' && is_numeric($amount) ? $amount : null,
                $currency,
            ),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    protected function byteFields(string $key, int|float|string|null $bytes): array
    {
        $raw = null;

        if ($bytes !== null && $bytes !== '' && is_numeric($bytes)) {
            $raw = (int) $bytes;
        }

        return [
            $key => $raw,
            $key.'_human' => BytePresenter::human($raw),
        ];
    }
}
