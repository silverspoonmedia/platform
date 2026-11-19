<?php

namespace App\Support\Encryption;

use Illuminate\Support\Facades\Cache;

class DecryptedValueCache
{
    public static function ciphertextFingerprint(string $storedValue): string
    {
        return hash('xxh128', $storedValue);
    }

    public function has(string $contextKey, string $storedValue): bool
    {
        $hash = self::ciphertextFingerprint($storedValue);

        if (Cache::get($this->pointerKey($contextKey)) !== $hash) {
            return false;
        }

        return Cache::has($this->valueKey($contextKey, $hash));
    }

    /**
     * @param  (callable(): mixed)  $resolver
     * @param  int|null  $ttl  Seconds; null = global decrypted_cache_max_ttl; 0 = forever until forget
     */
    public function remember(string $contextKey, string $storedValue, callable $resolver, ?int $ttl = null): mixed
    {
        $hash = self::ciphertextFingerprint($storedValue);
        $valueKey = $this->valueKey($contextKey, $hash);
        $pointerKey = $this->pointerKey($contextKey);

        $previousHash = Cache::get($pointerKey);

        if (is_string($previousHash) && $previousHash !== '' && $previousHash !== $hash) {
            Cache::forget($this->valueKey($contextKey, $previousHash));
        }

        $maxTtl = $ttl ?? $this->maxTtl();

        $value = $maxTtl > 0
            ? Cache::remember($valueKey, $maxTtl, $resolver)
            : Cache::rememberForever($valueKey, $resolver);

        if ($maxTtl > 0) {
            Cache::put($pointerKey, $hash, $maxTtl);
        } else {
            Cache::forever($pointerKey, $hash);
        }

        return $value;
    }

    public function forgetByContext(string $contextKey): void
    {
        $pointerKey = $this->pointerKey($contextKey);
        $hash = Cache::get($pointerKey);

        if (is_string($hash) && $hash !== '') {
            Cache::forget($this->valueKey($contextKey, $hash));
        }

        Cache::forget($pointerKey);
    }

    public function forget(string $contextKey): void
    {
        $this->forgetByContext($contextKey);
    }

    public function flushAll(): void
    {
        // POC uses key-per-record cache; full flush is store-dependent.
        // Artisan command documents limitation; Redis SCAN flush deferred to Phase 2.
    }

    private function maxTtl(): int
    {
        return (int) config('encryption.decrypted_cache_max_ttl', 86400);
    }

    private function pointerKey(string $contextKey): string
    {
        return $this->keyPrefix().'.pointer.'.$contextKey;
    }

    private function valueKey(string $contextKey, string $ciphertextHash): string
    {
        return $this->keyPrefix().'.'.$contextKey.'.'.$ciphertextHash;
    }

    private function keyPrefix(): string
    {
        return (string) config('encryption.decrypted_cache_prefix', 'encryption.decrypted');
    }
}
