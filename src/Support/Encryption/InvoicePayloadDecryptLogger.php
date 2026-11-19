<?php

namespace App\Support\Encryption;

use Illuminate\Support\Facades\Log;

/**
 * Traces why invoice gateway payload columns are decrypted (read or compare).
 */
class InvoicePayloadDecryptLogger
{
    private const GATEWAY_PAYLOAD_FIELDS = [
        'gateway_request_payload',
        'gateway_response_payload',
    ];

    public function shouldLog(DecryptionContext $context): bool
    {
        if (! (bool) config('encryption.log_invoice_payload_decrypts', false)) {
            return false;
        }

        return $context->resourceType === 'invoices'
            && in_array($context->field, self::GATEWAY_PAYLOAD_FIELDS, true);
    }

    public function log(DecryptionContext $context, bool $cacheHit, string $operation): void
    {
        if (! $this->shouldLog($context)) {
            return;
        }

        $payload = [
            'invoice_id' => $context->resourceId,
            'field' => $context->field,
            'operation' => $operation,
            'cache_hit' => $cacheHit,
            'purpose' => $context->purpose,
            'sources' => DecryptSourceContext::labels(),
            'source_stack' => DecryptSourceContext::stack(),
            'inferred_caller' => $this->inferCaller(),
        ];

        $request = request();

        if ($request !== null) {
            $payload['http'] = [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
            ];
        }

        if (app()->runningInConsole()) {
            $payload['console'] = [
                'argv' => $_SERVER['argv'] ?? [],
            ];
        }

        Log::channel('invoice_payload_decrypt')->info('invoice_payload_decrypt', $payload);
    }

    private function inferCaller(): ?string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
            $class = $frame['class'] ?? '';

            if ($class === '' || str_contains($class, 'App\\Support\\Encryption\\')) {
                continue;
            }

            if (str_starts_with($class, 'App\\')) {
                return $class.($frame['type'] ?? '').($frame['function'] ?? '');
            }
        }

        return null;
    }
}
