<?php

namespace App\Support\Settings\Repositories\Eloquent;

use App\Support\Settings\Models\SystemSetting;
use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Support\Settings\SettingRegistry;
use App\Support\Settings\SettingType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettingRepository implements SystemSettingRepositoryInterface
{
    private const CACHE_KEY = 'system_settings.snapshot';

    public function snapshot(): array
    {
        $snapshot = Cache::get(self::CACHE_KEY);

        if (is_array($snapshot)) {
            return $snapshot;
        }

        // Pre-migration callers (fresh install, early artisan, tests without
        // RefreshDatabase) must not blow up on a missing table — an empty
        // snapshot makes SettingsReader fall back to config literals.
        if (! Schema::hasTable('system_settings')) {
            return [];
        }

        return $this->rebuildCache();
    }

    public function snapshotForGroup(string $group): array
    {
        return array_filter(
            $this->snapshot(),
            fn (mixed $_, string $key): bool => (SettingRegistry::get($key)['group'] ?? null) === $group,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Rebuild the forever cache from the database and return the fresh snapshot.
     *
     * @return array<string, mixed>
     */
    public function rebuildCache(): array
    {
        $snapshot = $this->buildSnapshot();
        Cache::forever(self::CACHE_KEY, $snapshot);

        return $snapshot;
    }

    public function upsertMany(array $values, ?string $updatedByUserId = null): void
    {
        foreach ($values as $key => $rawValue) {
            $definition = SettingRegistry::get((string) $key);

            if ($definition === null) {
                continue;
            }

            $castValue = $definition['type']->cast($rawValue);

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $definition['group'],
                    'type' => $definition['type']->value,
                    'value' => $castValue,
                    'updated_by' => $updatedByUserId,
                ],
            );
        }

        $this->rebuildCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(): array
    {
        $rows = SystemSetting::query()->get()->keyBy('key');
        $snapshot = [];

        foreach ($rows as $key => $row) {
            $definition = SettingRegistry::get($key);
            $type = $definition !== null
                ? $definition['type']
                : SettingType::tryFrom($row->type) ?? SettingType::String;

            $snapshot[$key] = $type->cast($row->value);
        }

        return $snapshot;
    }
}
