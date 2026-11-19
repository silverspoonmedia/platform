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
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        // A subscriber, not a `handle*` listener: framework event discovery only
        // finds `handle*` methods, so the file-log auth trail registers here.
        Event::subscribe(LogAuthenticationEvents::class);
    }
}
