<?php

namespace App\Domain\Identity\Tests\Feature\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Outbound email must follow the RECIPIENT's saved locale, not the locale of
 * the request that triggered the send. A forgot-password request is made by a
 * guest, so the only reliable language signal is the target user's
 * `users.locale` (exposed via User::preferredLocale()).
 *
 * These tests render the mail directly (no queue/mailer needed) and assert the
 * copy is translated — both the shared laravel-lang lines and the
 * application-owned API hint (lang/{locale}/mail.php). Guards against the
 * regression where the override hardcoded English and ignored the locale.
 */
class ResetPasswordNotificationLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_renders_in_recipient_locale_indonesian(): void
    {
        // The app is running in English; the recipient prefers Indonesian.
        app()->setLocale('en');
        $user = User::factory()->create(['locale' => 'id']);

        $this->assertSame('id', $user->preferredLocale());

        $mail = $this->renderForRecipient($user);

        // Subject + a shared line + the application-owned API hint, all in id.
        $this->assertSame(__('Reset your password', [], 'id'), $mail['subject']);
        $this->assertStringContainsString(__('mail.reset_password_api_hint', [], 'id'), $mail['body']);
        $this->assertStringContainsString('POST /api/v1/auth/reset-password', $mail['body']);
    }

    public function test_email_does_not_leak_hardcoded_english_for_id_recipient(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $mail = $this->renderForRecipient($user);

        // The old override hardcoded this English subject; it must be gone.
        $this->assertNotSame('Reset Password Notification', $mail['subject']);
        $this->assertStringContainsString(__('mail.reset_password_api_hint', [], 'id'), $mail['body']);
    }

    public function test_preferred_locale_is_null_when_unsupported_or_unset(): void
    {
        $this->assertNull(User::factory()->create(['locale' => null])->preferredLocale());
        $this->assertNull(User::factory()->create(['locale' => 'zz'])->preferredLocale());
    }

    /**
     * Render the notification the way Laravel does for a notifiable that
     * implements HasLocalePreference: within the recipient's locale.
     *
     * @return array{subject: string, body: string}
     */
    private function renderForRecipient(User $user): array
    {
        $original = app()->getLocale();
        app()->setLocale($user->preferredLocale() ?? config('app.locale'));

        try {
            $mail = (new ResetPasswordNotification('reset-token'))->toMail($user);

            return [
                'subject' => $mail->subject,
                'body' => implode("\n", array_merge($mail->introLines, $mail->outroLines)),
            ];
        } finally {
            app()->setLocale($original);
        }
    }
}
