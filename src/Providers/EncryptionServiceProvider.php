<?php

namespace App\Providers;

use App\Support\Encryption\Auth\EnvelopePasswordBrokerManager;
use App\Support\Encryption\Cache\SensitiveCacheValue;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\Session\EncryptedDatabaseSessionHandler;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\LocalKmsDekClient;
use App\Support\Oci\Kms\OciKmsClient;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class EncryptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KmsDekClientInterface::class, function (): KmsDekClientInterface {
            if (config('encryption.kms_enabled')) {
                return new OciKmsClient;
            }

            return new LocalKmsDekClient;
        });

        $this->app->singleton(DecryptedValueCache::class);
        $this->app->singleton(SensitiveCacheValue::class);
        $this->app->singleton(EnvelopeEncryptionService::class);

        $this->app->singleton('auth.password', function ($app) {
            return new EnvelopePasswordBrokerManager($app);
        });

        $this->app->booting(function (): void {
            if (
                config('session.driver') === 'database'
                && (bool) config('encryption.encrypt_sessions', false)
            ) {
                config(['session.driver' => 'encrypted_database']);
            }
        });
    }

    public function boot(): void
    {
        Session::extend('encrypted_database', function ($app) {
            $config = $app['config']['session'];
            $connection = $app['db']->connection($config['connection'] ?? null);
            $table = $config['table'];
            $lifetime = $config['lifetime'];

            return new EncryptedDatabaseSessionHandler(
                $connection,
                $table,
                $lifetime,
                $app,
                $app->make(EnvelopeEncryptionService::class),
            );
        });
    }
}
