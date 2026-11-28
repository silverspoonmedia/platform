<?php

namespace App\Support\CoreHub;

/**
 * Kernel-owned snapshot shape of one identity, as the hub last published it.
 *
 * Data only: every field is a scalar, a list of scalars or a `DateTimeInterface`,
 * so a consumer can rebuild it from a JSON payload it received over the wire and
 * store it in a cache without importing anything else. `version` is what makes
 * last-write-wins safe — a push that is older than the row already stored is
 * dropped, because the hub retries and a retry must not rewind state.
 */
final readonly class IdentitySnapshot
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $externalId,
        public string $product,
        public string $email,
        public string $name,
        public array $roles,
        public array $permissions,
        public int $version,
        public ?\DateTimeInterface $syncedAt = null,
        public ?\DateTimeInterface $disabledAt = null,
    ) {}

    public function isDisabled(): bool
    {
        return $this->disabledAt !== null;
    }

    public static function fromArray(array $payload): self
    {
        $ts = static fn (mixed $value): ?\DateTimeImmutable => is_string($value) && $value !== ''
            ? new \DateTimeImmutable($value)
            : null;

        return new self(
            externalId: (string) ($payload['external_id'] ?? ''),
            product: (string) ($payload['product'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            roles: array_values(array_filter((array) ($payload['roles'] ?? []), 'is_string')),
            permissions: array_values(array_filter((array) ($payload['permissions'] ?? []), 'is_string')),
            version: (int) ($payload['version'] ?? 0),
            syncedAt: $ts($payload['synced_at'] ?? null),
            disabledAt: $ts($payload['disabled_at'] ?? null),
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
            'email' => $this->email,
            'name' => $this->name,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
            'version' => $this->version,
            'synced_at' => $this->syncedAt?->format(DATE_ATOM),
            'disabled_at' => $this->disabledAt?->format(DATE_ATOM),
        ];
    }
}
