<?php

namespace App\Support;

/**
 * Parses committed .env template files for catalog validation.
 */
final class EnvironmentTemplateParser
{
    /**
     * @return array<string, string> name => raw_value (last assignment wins)
     */
    public static function parseUniqueAssignments(string $contents): array
    {
        $unique = [];

        foreach (self::parseAssignments($contents) as $assignment) {
            $unique[$assignment['name']] = $assignment['raw_value'];
        }

        return $unique;
    }

    /**
     * @return list<array{name: string, raw_value: string}>
     */
    public static function parseAssignments(string $contents): array
    {
        $assignments = [];

        foreach (preg_split('/\R/', $contents) as $line) {
            $parsed = self::parseLine($line);

            if ($parsed !== null) {
                $assignments[] = $parsed;
            }
        }

        return $assignments;
    }

    /**
     * @return array{name: string, raw_value: string}|null
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (! preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
            return null;
        }

        return [
            'name' => $matches[1],
            'raw_value' => self::stripInlineComment($matches[2]),
        ];
    }

    private static function stripInlineComment(string $value): string
    {
        $value = rtrim($value);

        if (str_starts_with($value, '"')) {
            $length = strlen($value);
            $escaped = false;

            for ($index = 1; $index < $length; $index++) {
                $character = $value[$index];

                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($character === '"') {
                    return rtrim(substr($value, 0, $index + 1));
                }
            }

            return $value;
        }

        $commentPosition = strpos($value, ' #');

        if ($commentPosition === false) {
            return trim($value);
        }

        return trim(substr($value, 0, $commentPosition));
    }
}
