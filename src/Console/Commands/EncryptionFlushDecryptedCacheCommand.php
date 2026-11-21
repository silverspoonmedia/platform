<?php

namespace App\Console\Commands;

use App\Support\Encryption\DecryptedValueCache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Invalidate decrypted plaintext for one context, and nothing else.
 *
 * The cache is keyed per `context` + ciphertext fingerprint, and plaintext is
 * forgotten on encrypt / cast `set`, so a flush is only needed after a secret
 * rewrite or key rotation. There is **no bulk flush**: enumerating a cache store
 * is driver-specific (Redis SCAN, a database LIKE, an `array` diff) and the cache
 * contract is driver-agnostic (`docs/platform/cache-policy.md`). Asking for one
 * without a context is therefore an error, not a no-op.
 */
#[AsCommand(name: 'encryption:flush-decrypted-cache')]
class EncryptionFlushDecryptedCacheCommand extends Command
{
    protected $signature = 'encryption:flush-decrypted-cache {context? : Optional decryption context key suffix}';

    protected $description = 'Flush decrypted-value cache entries used by envelope encryption';

    public function __construct(
        private readonly DecryptedValueCache $decryptedValueCache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $context = $this->argument('context');

        if (is_string($context) && $context !== '') {
            $this->decryptedValueCache->forget($context);
            $this->info('Flushed decrypted cache context: '.$context);

            return self::SUCCESS;
        }

        $this->error('Bulk decrypted-cache flush is not available: the cache is flushed per context. Pass a context key suffix.');
        $this->line('Example: php artisan encryption:flush-decrypted-cache invoices.gateway_response_payload.{uuid}');

        return self::FAILURE;
    }
}
