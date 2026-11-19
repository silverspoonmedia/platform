<?php

namespace App\Providers;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Support\Settings\Repositories\Eloquent\SystemSettingRepository;
use App\Support\Settings\SettingsReader;
use App\Support\Settings\SettingsService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->singleton(SettingsReader::class);
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        if (Schema::hasTable('system_settings')) {
            $this->app->make(SystemSettingRepositoryInterface::class)->snapshot();
        }

        // Laravel Mail reads config('mail.from') at build time; apply Tier B overrides
        // without runtime config()->set() (Octane-safe).
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $address = trim((string) setting_config('mail.from.address', ''));
            if ($address === '') {
                return;
            }

            $name = (string) setting_config('mail.from.name', '');
            $event->message->from(
                $name !== '' ? new Address($address, $name) : new Address($address)
            );
        });
    }
}
