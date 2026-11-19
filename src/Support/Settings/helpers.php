<?php

use App\Support\Settings\SettingsReader;

if (! function_exists('setting')) {
    /**
     * Read a registry key with DB override → config fallback.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsReader::class)->get($key, $default);
    }
}

if (! function_exists('setting_config')) {
    /**
     * Read by config path when the path is registered in SettingRegistry.
     */
    function setting_config(string $configPath, mixed $default = null): mixed
    {
        return app(SettingsReader::class)->config($configPath, $default);
    }
}
