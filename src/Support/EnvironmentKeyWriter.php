<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Reads and writes APP_KEY / APP_PREVIOUS_KEYS in .env files using a stable
 * anchor line (APP_PREVIOUS_KEYS) so key generation works regardless of
 * config('app.key') vs on-disk mismatch or quoted values.
 */
class EnvironmentKeyWriter
{
    public function readAppKey(string $envPath): ?string
    {
        $value = $this->readEnvValue($envPath, 'APP_KEY');

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    public function readPreviousKeys(string $envPath): array
    {
        $raw = $this->readEnvValue($envPath, 'APP_PREVIOUS_KEYS');

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $key): string => trim($key, " \t\n\r\0\x0B\"'"),
            explode(',', $raw),
        )));
    }

    public function ensureAnchorPresent(string $envPath): void
    {
        $contents = File::get($envPath);

        if (preg_match('/^APP_PREVIOUS_KEYS=/m', $contents)) {
            return;
        }

        $updated = preg_replace(
            '/^(APP_KEY=.*)$/m',
            "$1\nAPP_PREVIOUS_KEYS=\"\"",
            $contents,
            count: $count,
        );

        if ($count === 0) {
            throw new RuntimeException('APP_KEY line not found in environment file.');
        }

        File::put($envPath, $updated);
    }

    public function writeAppKey(string $envPath, ?string $rotateFrom = null, ?string $cipher = null): string
    {
        $this->ensureAnchorPresent($envPath);

        $cipher ??= (string) config('app.cipher', 'AES-256-CBC');
        $newKey = 'base64:'.base64_encode(Encrypter::generateKey($cipher));

        $contents = File::get($envPath);
        $previousKeys = $this->readPreviousKeys($envPath);

        if ($rotateFrom !== null && $rotateFrom !== '') {
            $previousKeys = $this->appendPreviousKey($previousKeys, $rotateFrom);
        }

        $previousKeysValue = $this->formatPreviousKeysValue($previousKeys);

        $updated = preg_replace(
            '/^APP_KEY=.*\R^APP_PREVIOUS_KEYS=.*$/m',
            'APP_KEY="'.$newKey."\"\nAPP_PREVIOUS_KEYS=\"{$previousKeysValue}\"",
            $contents,
            count: $count,
        );

        if ($count === 0) {
            throw new RuntimeException(
                'Could not update APP_KEY — ensure APP_PREVIOUS_KEYS follows APP_KEY on the next line.',
            );
        }

        File::put($envPath, $updated);

        return $newKey;
    }

    /**
     * @param  array<int, string>  $previousKeys
     * @return array<int, string>
     */
    private function appendPreviousKey(array $previousKeys, string $key): array
    {
        if (in_array($key, $previousKeys, true)) {
            return $previousKeys;
        }

        $previousKeys[] = $key;

        return $previousKeys;
    }

    /**
     * @param  array<int, string>  $previousKeys
     */
    private function formatPreviousKeysValue(array $previousKeys): string
    {
        return implode(',', $previousKeys);
    }

    private function readEnvValue(string $envPath, string $name): string
    {
        $contents = File::get($envPath);

        if (! preg_match('/^'.preg_quote($name, '/').'=(.*)$/m', $contents, $matches)) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }

    public function writeValueIfEmpty(string $envPath, string $name, string $value): bool
    {
        if ($this->readEnvValue($envPath, $name) !== '') {
            return false;
        }

        $formattedValue = '"'.$this->escapeQuotedValue($value).'"';
        $contents = File::get($envPath);

        if (preg_match('/^'.preg_quote($name, '/').'=.*$/m', $contents)) {
            $updated = preg_replace(
                '/^'.preg_quote($name, '/').'=.*$/m',
                $name.'='.$formattedValue,
                $contents,
            );
        } else {
            $updated = rtrim($contents).PHP_EOL.$name.'='.$formattedValue.PHP_EOL;
        }

        File::put($envPath, $updated);

        return true;
    }

    public function writeBlindIndexPepperIfEmpty(string $envPath): bool
    {
        return $this->writeValueIfEmpty(
            $envPath,
            'ENCRYPTION_BLIND_INDEX_PEPPER',
            bin2hex(random_bytes(32)),
        );
    }

    private function escapeQuotedValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
