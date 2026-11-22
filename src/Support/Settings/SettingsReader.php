<?php

namespace App\Support\Settings;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;

/**
 * Octane-safe runtime reader: cached DB snapshot → config() literal fallback.
 */
final class SettingsReader
{
    public function __construct(
        private readonly SystemSettingRepositoryInterface $repository,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $snapshot = $this->repository->snapshot();

        if (array_key_exists($key, $snapshot)) {
            return $snapshot[$key];
        }

        $definition = SettingRegistry::get($key);

        if ($definition !== null) {
            $fromConfig = config($definition['config']);

            if ($fromConfig !== null) {
                return $definition['type']->cast($fromConfig);
            }
        }

        return $default;
    }

    /**
     * Resolve a config path via registry index or direct config().
     *
     * Parent array paths (e.g. the vision block a product configures) overlay any registered
     * descendant leaf overrides so mixed Tier A/B arrays stay coherent.
     */
    public function config(string $configPath, mixed $default = null): mixed
    {
        $registryKey = SettingRegistry::configPathIndex()[$configPath] ?? null;

        if ($registryKey !== null) {
            return $this->get($registryKey, $default);
        }

        $fromConfig = config($configPath, $default);

        if (! is_array($fromConfig)) {
            return $fromConfig;
        }

        return $this->overlayRegisteredDescendants($configPath, $fromConfig);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function overlayRegisteredDescendants(string $configPath, array $base): array
    {
        $prefix = $configPath.'.';
        $result = $base;

        foreach (SettingRegistry::configPathIndex() as $path => $registryKey) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $relative = substr($path, strlen($prefix));
            $segments = explode('.', $relative);
            $value = $this->get($registryKey);
            $ref = &$result;

            foreach ($segments as $i => $segment) {
                if ($i === count($segments) - 1) {
                    $ref[$segment] = $value;

                    continue;
                }

                if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }

                $ref = &$ref[$segment];
            }

            unset($ref);
        }

        return $result;
    }
}
