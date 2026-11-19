<?php

namespace App\Support\Settings\Repositories\Contracts;

interface SystemSettingRepositoryInterface
{
    /**
     * Cached primitive snapshot keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array;

    /**
     * @return array<string, mixed>
     */
    public function snapshotForGroup(string $group): array;

    public function flushCache(): void;

    /**
     * Rebuild the forever cache from the database and return the fresh snapshot.
     *
     * @return array<string, mixed>
     */
    public function rebuildCache(): array;

    /**
     * @param  array<string, mixed>  $values  key => raw value
     */
    public function upsertMany(array $values, ?string $updatedByUserId = null): void;
}
