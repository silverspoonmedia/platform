<?php

namespace App\Support\Encryption\Session;

use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Session\DatabaseSessionHandler;
use SessionHandlerInterface;

class EncryptedDatabaseSessionHandler extends DatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        ConnectionInterface $connection,
        string $table,
        int $lifetime,
        ?Container $container,
        private readonly EnvelopeEncryptionService $encryption,
    ) {
        parent::__construct($connection, $table, $lifetime, $container);
    }

    public function read($sessionId): string
    {
        $payload = parent::read($sessionId);

        if ($payload === '' || ! EncryptedEnvelope::isEnvelopeJson($payload)) {
            return $payload;
        }

        return $this->encryption->decrypt($payload, $this->context($sessionId));
    }

    public function write($sessionId, $data): bool
    {
        if ($data === '') {
            return parent::write($sessionId, $data);
        }

        $envelope = $this->encryption->encrypt($data, $this->context($sessionId));

        return parent::write($sessionId, $envelope);
    }

    private function context(string $sessionId): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: 'sessions',
            resourceId: $sessionId,
            field: 'payload',
            purpose: 'session_read',
        );
    }
}
