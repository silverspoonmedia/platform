<?php

namespace App\Support\Encryption\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;

class EnvelopePasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config): EnvelopeDatabaseTokenRepository
    {
        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new EnvelopeDatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
