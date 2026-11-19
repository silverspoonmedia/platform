<?php

namespace App\Support\Config;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Foundation\Application;

class FreshConfigurationLoader
{
    /**
     * Boot a new application instance and load configuration from the active `.env`.
     *
     * @return array<string, mixed>
     */
    public function load(Application $baseApplication): array
    {
        /** @var Application $app */
        $app = require $baseApplication->bootstrapPath('app.php');

        $app->useStoragePath($baseApplication->storagePath());
        $app->loadEnvironmentFrom('.env');

        $cachedPath = $app->getCachedConfigPath();

        if (is_file($cachedPath)) {
            @unlink($cachedPath);
        }

        $app->make(ConsoleKernelContract::class)->bootstrap();

        return $app['config']->all();
    }

    public function loadRepository(Application $baseApplication): Repository
    {
        return new Repository($this->load($baseApplication));
    }
}
