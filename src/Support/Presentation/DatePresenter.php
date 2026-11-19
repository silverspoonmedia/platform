<?php

namespace App\Support\Presentation;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Locale-aware date presentation for API Resources.
 *
 * Raw ISO-8601 stays the machine contract; companions are display-only.
 */
final class DatePresenter
{
    public const ABSOLUTE_FORMAT = 'd M Y H:i';

    public static function iso(CarbonInterface|string|null $value): ?string
    {
        $carbon = self::toCarbon($value);

        return $carbon?->toIso8601String();
    }

    public static function formatted(CarbonInterface|string|null $value): ?string
    {
        $carbon = self::toCarbon($value);

        if ($carbon === null) {
            return null;
        }

        return $carbon->locale(app()->getLocale())->translatedFormat(self::ABSOLUTE_FORMAT);
    }

    public static function human(CarbonInterface|string|null $value): ?string
    {
        $carbon = self::toCarbon($value);

        if ($carbon === null) {
            return null;
        }

        return $carbon->locale(app()->getLocale())->diffForHumans();
    }

    /**
     * Dual-field pack: raw ISO + absolute + relative companions.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [iso, formatted, human]
     */
    public static function pack(CarbonInterface|string|null $value): array
    {
        $carbon = self::toCarbon($value);

        if ($carbon === null) {
            return [null, null, null];
        }

        $localized = $carbon->locale(app()->getLocale());

        return [
            $carbon->toIso8601String(),
            $localized->translatedFormat(self::ABSOLUTE_FORMAT),
            $localized->diffForHumans(),
        ];
    }

    private static function toCarbon(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }
}
