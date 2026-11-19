<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reorders and reformats `.env` using the active profile template layout while preserving values.
 */
class EnvironmentCurator
{
    private const ORPHAN_SECTION = '# --- Extra keys (not in template) ---';

    public function __construct(
        private readonly EnvironmentProfileManager $profiles,
        private readonly EnvironmentMutationGuard $guard,
    ) {}

    /**
     * @return array{changed: bool, added_keys: array<int, string>, orphan_keys: array<int, string>, lines_before: int, lines_after: int}
     */
    public function curate(bool $dryRun = false, bool $syncSnapshot = false, bool $forcePastBackup = false): array
    {
        $profile = $this->profiles->activeProfile();

        if ($profile === null) {
            throw new InvalidArgumentException(
                'Cannot determine active profile. Set `.env.profile` or ensure APP_ENV is local, staging, or production.',
            );
        }

        $templatePath = $this->profiles->templatePath($profile);
        $envPath = $this->profiles->envPath();

        if (! File::exists($envPath)) {
            throw new RuntimeException('Active .env not found.');
        }

        if (! File::exists($templatePath)) {
            throw new RuntimeException("Profile template not found for [{$profile}].");
        }

        $before = File::get($envPath);
        $curated = $this->buildCuratedContents(
            File::get($templatePath),
            $before,
        );

        $currentValues = EnvironmentTemplateParser::parseUniqueAssignments($before);
        $templateKeys = array_keys(EnvironmentTemplateParser::parseUniqueAssignments(File::get($templatePath)));
        $addedKeys = array_values(array_diff($templateKeys, array_keys($currentValues)));
        $orphanKeys = array_values(array_diff(array_keys($currentValues), $templateKeys));
        sort($orphanKeys);

        $result = [
            'changed' => $curated !== rtrim($before).PHP_EOL && $curated !== $before,
            'added_keys' => $addedKeys,
            'orphan_keys' => $orphanKeys,
            'lines_before' => substr_count($before, PHP_EOL) + (str_ends_with($before, PHP_EOL) ? 0 : 1),
            'lines_after' => substr_count($curated, PHP_EOL) + (str_ends_with($curated, PHP_EOL) ? 0 : 1),
        ];

        if ($dryRun) {
            return $result;
        }

        if (! $result['changed'] && ! $syncSnapshot) {
            return $result;
        }

        $backupPaths = [$envPath => '.env'];

        if ($syncSnapshot && $this->profiles->snapshotExists($profile)) {
            $backupPaths[$this->profiles->profilePath($profile)] = '.env.'.$profile;
        }

        $this->guard->run(
            'env:curate',
            function () use ($curated, $envPath, $syncSnapshot, $profile): void {
                File::put($envPath, $curated);

                if ($syncSnapshot) {
                    File::copy($envPath, $this->profiles->profilePath($profile));
                }
            },
            $backupPaths,
            $forcePastBackup,
        );

        return $result;
    }

    public function buildCuratedContents(string $templateContents, string $envContents): string
    {
        $values = EnvironmentTemplateParser::parseUniqueAssignments($envContents);
        $templateKeys = [];
        $lines = [];

        foreach (preg_split('/\R/', $templateContents) as $line) {
            $parsed = $this->parseAssignmentLine($line);

            if ($parsed !== null) {
                $templateKeys[$parsed['name']] = true;
                $rawValue = $values[$parsed['name']] ?? $parsed['raw_value'];
                $lines[] = $parsed['name'].'='.EnvironmentVariableCatalog::formatRawValue(
                    EnvironmentVariableCatalog::type($parsed['name']),
                    $rawValue,
                );

                continue;
            }

            $lines[] = rtrim($line, "\r\n");
        }

        $orphans = array_diff(array_keys($values), array_keys($templateKeys));
        sort($orphans);

        if ($orphans !== []) {
            if ($lines !== [] && end($lines) !== '') {
                $lines[] = '';
            }

            $lines[] = self::ORPHAN_SECTION;
            $lines[] = '# Keys present in .env but not in the profile template.';

            foreach ($orphans as $key) {
                $lines[] = $key.'='.EnvironmentVariableCatalog::formatRawValue(
                    EnvironmentVariableCatalog::type($key),
                    $values[$key],
                );
            }
        }

        $output = implode(PHP_EOL, $lines);

        if ($output !== '' && ! str_ends_with($output, PHP_EOL)) {
            $output .= PHP_EOL;
        }

        return $output;
    }

    public function diffSummary(string $before, string $after): string
    {
        $beforeLines = explode("\n", str_replace("\r\n", "\n", $before));
        $afterLines = explode("\n", str_replace("\r\n", "\n", $after));

        return sprintf(
            'Lines: %d → %d',
            count($beforeLines),
            count($afterLines),
        );
    }

    /**
     * @return array{name: string, raw_value: string}|null
     */
    private function parseAssignmentLine(string $line): ?array
    {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        $parsed = EnvironmentTemplateParser::parseAssignments($trimmed);

        return $parsed[0] ?? null;
    }
}
