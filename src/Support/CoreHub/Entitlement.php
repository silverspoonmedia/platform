<?php

namespace App\Support\CoreHub;

/**
 * Kernel-owned snapshot shape of the paid layer the hub owns for one identity.
 *
 * The hub is the only writer: a checkout, a renewal, a grace window and an expiry
 * all happen there, and each transition is pushed to every registered consumer as
 * a new `version`. A consumer reads it, caches it, and never computes it — the
 * consequence of getting the money math wrong twice, in two codebases, is what
 * this class exists to prevent.
 *
 * `tier` answers "what did this person buy" (`paid`, `trial`, `free`); `status`
 * answers "may they use it right now" (`active`, `grace`, `expired`,
 * `cancelled`). They are separate on purpose: a `grace` row with `tier = paid`
 * still renders a paid badge while uploads are blocked.
 */
final readonly class Entitlement
{
    public const TIER_PAID = 'paid';

    public const TIER_TRIAL = 'trial';

    public const TIER_FREE = 'free';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE = 'grace';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param  list<string>  $features
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $externalId,
        public string $product,
        public string $code,
        public string $tier,
        public string $status,
        public ?\DateTimeInterface $validFrom = null,
        public ?\DateTimeInterface $validTo = null,
        public int $quantity = 1,
        public array $features = [],
        public array $metadata = [],
        public int $version = 0,
        public ?\DateTimeInterface $syncedAt = null,
    ) {}

    /**
     * Paid layer that may be used right now, including the grace window.
     */
    public function isUsable(): bool
    {
        return $this->tier !== self::TIER_FREE
            && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_GRACE], true);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * What a consumer reads before the hub ever published: not paid, not usable.
     */
    public static function none(string $externalId, string $product, string $code = ''): self
    {
        return new self(
            externalId: $externalId,
            product: $product,
            code: $code,
            tier: self::TIER_FREE,
            status: self::STATUS_EXPIRED,
        );
    }

    public static function fromArray(array $payload): self
    {
        $ts = static fn (mixed $value): ?\DateTimeImmutable => is_string($value) && $value !== ''
            ? new \DateTimeImmutable($value)
            : null;

        return new self(
            externalId: (string) ($payload['external_id'] ?? ''),
            product: (string) ($payload['product'] ?? ''),
            code: (string) ($payload['code'] ?? ''),
            tier: (string) ($payload['tier'] ?? self::TIER_FREE),
            status: (string) ($payload['status'] ?? self::STATUS_EXPIRED),
            validFrom: $ts($payload['valid_from'] ?? null),
            validTo: $ts($payload['valid_to'] ?? null),
            quantity: max(0, (int) ($payload['quantity'] ?? 0)),
            features: array_values(array_filter((array) ($payload['features'] ?? []), 'is_string')),
            metadata: (array) ($payload['metadata'] ?? []),
            version: (int) ($payload['version'] ?? 0),
            syncedAt: $ts($payload['synced_at'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'product' => $this->product,
            'code' => $this->code,
            'tier' => $this->tier,
            'status' => $this->status,
            'valid_from' => $this->validFrom?->format(DATE_ATOM),
            'valid_to' => $this->validTo?->format(DATE_ATOM),
            'quantity' => $this->quantity,
            'features' => $this->features,
            'metadata' => $this->metadata,
            'version' => $this->version,
            'synced_at' => $this->syncedAt?->format(DATE_ATOM),
        ];
    }
}
