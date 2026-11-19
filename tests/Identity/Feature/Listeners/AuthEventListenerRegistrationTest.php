<?php

namespace App\Domain\Identity\Tests\Feature\Listeners;

use App\Domain\ActivityLog\Support\AuthActivityRecorder;
use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginSuccessful;
use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Events\TwoFactorChallengeIssued;
use App\Domain\Identity\Events\TwoFactorDisabled;
use App\Domain\Identity\Events\TwoFactorEnabled;
use App\Domain\Identity\Events\TwoFactorLoginFailed;
use App\Domain\Identity\Events\UserLoggedOut;
use App\Domain\Identity\Listeners\LogAuthenticationEvents;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the two auth trails stay wired after the kernel moved into
 * `silverspoonmedia/platform`: the file log (kernel) and the activity log
 * (`silverspoonmedia/audit`).
 *
 * Both are subscribers (`subscribe()`), not `handle*` listeners — framework
 * event discovery only finds `handle*` methods, so each owner registers its own
 * subscriber. Either half going missing silently drops one trail.
 *
 * Counted through `Event::getRawListeners()`: `Event::getListeners()` returns a
 * closure per subscriber, so the class names are only visible in the raw map.
 */
class AuthEventListenerRegistrationTest extends TestCase
{
    /**
     * @return array<string, class-string>
     */
    public static function authEventsProvider(): array
    {
        return [
            'login successful' => [LoginSuccessful::class],
            'login failed' => [LoginFailed::class],
            'logout' => [UserLoggedOut::class],
            'two factor challenge' => [TwoFactorChallengeIssued::class],
            'two factor login failed' => [TwoFactorLoginFailed::class],
            'password changed' => [PasswordChanged::class],
            'two factor enabled' => [TwoFactorEnabled::class],
            'two factor disabled' => [TwoFactorDisabled::class],
        ];
    }

    /**
     * @param  class-string  $event
     */
    #[DataProvider('authEventsProvider')]
    public function test_auth_event_has_expected_listener_count(string $event): void
    {
        $listeners = $this->registeredSubscribers($event);

        $this->assertCount(
            2,
            $listeners,
            "Listener registration drifted for {$event}. Expected LogAuthenticationEvents "
            .'+ AuthActivityRecorder, found: '.implode(', ', $listeners),
        );

        foreach ([LogAuthenticationEvents::class, AuthActivityRecorder::class] as $expected) {
            $this->assertContains(
                $expected,
                $listeners,
                $expected.' is not registered for '.$event.'.',
            );
        }
    }

    /**
     * Classes the dispatcher holds for an event: a plain listener is stored as
     * itself or as a `[class, method]` pair, a subscriber as the pair the
     * dispatcher built for it, and a queued listener hides behind a closure.
     *
     * @param  class-string  $event
     * @return list<string>
     */
    private function registeredSubscribers(string $event): array
    {
        $classes = [];

        foreach (app('events')->getRawListeners()[$event] ?? [] as $listener) {
            if (is_string($listener)) {
                $classes[] = $listener;

                continue;
            }

            if (is_array($listener) && isset($listener[0])) {
                $classes[] = is_object($listener[0]) ? $listener[0]::class : (string) $listener[0];

                continue;
            }

            if ($listener instanceof \Closure) {
                $classes[] = (new \ReflectionFunction($listener))->getClosureScopeClass()?->getName();
            }
        }

        return array_values(array_unique(array_filter($classes)));
    }
}
