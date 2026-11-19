<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Support\Encryption\Casts\EnvelopeEncrypted;
use App\Support\Encryption\DecryptionContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityOAuthAccount extends Model
{
    use HasUuids;

    public const ENCRYPTION_RESOURCE_TYPE = 'identity_oauth_accounts';

    public const ENCRYPTION_FIELD_ACCESS = 'access_token';

    public const ENCRYPTION_FIELD_REFRESH = 'refresh_token';

    public const ENCRYPTION_PURPOSE = 'identity';

    protected $table = 'identity_oauth_accounts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'token_expires_at',
        'scopes',
        'connections_snapshot',
        'connections_synced_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => OAuthProvider::class,
            'access_token' => EnvelopeEncrypted::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_ACCESS.','
                .self::ENCRYPTION_PURPOSE,
            'refresh_token' => EnvelopeEncrypted::class.':'
                .self::ENCRYPTION_RESOURCE_TYPE.','
                .self::ENCRYPTION_FIELD_REFRESH.','
                .self::ENCRYPTION_PURPOSE,
            'token_expires_at' => 'datetime',
            'connections_snapshot' => 'array',
            'connections_synced_at' => 'datetime',
        ];
    }

    public function accessTokenDecryptionContext(): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: self::ENCRYPTION_RESOURCE_TYPE,
            resourceId: is_scalar($this->getKey()) ? (string) $this->getKey() : null,
            field: self::ENCRYPTION_FIELD_ACCESS,
            purpose: self::ENCRYPTION_PURPOSE,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->lte(now()->addMinute());
    }
}
