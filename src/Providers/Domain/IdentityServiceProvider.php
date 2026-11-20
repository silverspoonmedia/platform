<?php

namespace App\Providers\Domain;

use App\Domain\Identity\Listeners\LogAuthenticationEvents;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // The captcha gate is Identity policy: its requests read `hcaptcha.*`
        // through `setting_config()`, so the block ships with this package. A
        // kernel-only install would otherwise post to an empty verify URI.
        $this->mergeConfigFrom(__DIR__.'/../../../config/hcaptcha.php', 'hcaptcha');
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        // Identity's translations ship inside this package (`resources/lang`):
        // `api/auth.*`, `api/users.*`, `api/identity.*`, `forms/users.*`. The
        // loader keeps the host's `lang/` first, so a host copy still wins and
        // this only fills what the kernel owns. Without it a consumer installs
        // the kernel and renders every auth message as its raw key.
        $this->app['translator']->getLoader()->addPath(__DIR__.'/../../../resources/lang');

        // A subscriber, not a `handle*` listener: framework event discovery only
        // finds `handle*` methods, so the file-log auth trail registers here.
        Event::subscribe(LogAuthenticationEvents::class);
    }
}
