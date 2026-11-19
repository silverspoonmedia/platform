<?php

namespace App\Support\Encryption\Auth;

use App\Support\Encryption\BlindIndex;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

class EnvelopeDatabaseTokenRepository extends DatabaseTokenRepository
{
    public function __construct(
        ConnectionInterface $connection,
        HasherContract $hasher,
        string $table,
        string $hashKey,
        int $expires = 3600,
        int $throttle = 60,
        private readonly ?EnvelopeEncryptionService $encryption = null,
    ) {
        parent::__construct($connection, $hasher, $table, $hashKey, $expires, $throttle);
    }

    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        return [
            'email' => $email,
            'token' => $this->encryption()->encrypt($token),
            'token_blind_index' => BlindIndex::make($token),
            'created_at' => new Carbon,
        ];
    }

    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->getTable()->where(
            'email', $user->getEmailForPasswordReset()
        )->first();

        if (! $record || $this->tokenExpired($record['created_at'])) {
            return false;
        }

        if (! isset($record['token_blind_index'])) {
            return parent::exists($user, $token);
        }

        return hash_equals((string) $record['token_blind_index'], BlindIndex::make($token));
    }

    private function encryption(): EnvelopeEncryptionService
    {
        return $this->encryption ?? app(EnvelopeEncryptionService::class);
    }
}
