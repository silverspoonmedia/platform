<?php

namespace App\Support\Settings;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Validation\ValidationException;

final class SettingsService
{
    public function __construct(
        private readonly SystemSettingRepositoryInterface $repository,
        private readonly SettingsReader $reader,
    ) {}

    /**
     * @return array<string, array{value: mixed, source: string, type: string, options?: list<string>}>
     */
    public function presentGroup(string $group): array
    {
        $definitions = SettingRegistry::forGroup($group);
        $dbSnapshot = $this->repository->snapshotForGroup($group);
        $presented = [];

        foreach ($definitions as $key => $definition) {
            $inDb = array_key_exists($key, $dbSnapshot);
            $value = $inDb ? $dbSnapshot[$key] : config($definition['config']);

            $row = [
                'value' => $definition['type']->cast($value),
                'source' => $inDb ? 'database' : 'config',
                'type' => $definition['type']->value,
            ];

            if (! empty($definition['options']) && is_array($definition['options'])) {
                $row['options'] = array_values($definition['options']);
            }

            $presented[$key] = $row;
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{value: mixed, source: string, type: string}>
     */
    public function updateGroup(string $group, array $payload, ?string $userId): array
    {
        $allowed = SettingRegistry::forGroup($group);
        $filtered = [];

        foreach ($payload as $key => $value) {
            if (! array_key_exists($key, $allowed)) {
                throw ValidationException::withMessages([
                    $key => [__('settings.unknown_key', ['key' => $key])],
                ]);
            }

            $filtered[$key] = $value;
        }

        if ($filtered === []) {
            throw ValidationException::withMessages([
                'settings' => [__('settings.empty_payload')],
            ]);
        }

        $this->repository->upsertMany($filtered, $userId);

        return $this->presentGroup($group);
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return SettingRegistry::groups();
    }
}
