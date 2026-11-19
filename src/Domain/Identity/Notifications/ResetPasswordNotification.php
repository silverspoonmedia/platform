<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\User;
use App\Support\SpaPublicUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Mirrors the framework's password-reset email but points the action at the
     * Vue-Workers reset page when SPA_URL is set, otherwise the Blade reset
     * form. The API hint stays for clients that POST the token directly.
     *
     * All copy goes through the translator so the recipient's locale (see
     * {@see User::preferredLocale()}) is honoured: the shared lines
     * resolve from the laravel-lang JSON packs, and the API hint from the
     * application-owned `mail.php` file. Never hardcode English here.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = SpaPublicUrl::passwordReset(
            $this->token,
            $notifiable->getEmailForPasswordReset(),
        );

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(Lang::get('Reset your password'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $resetUrl)
            ->line(Lang::get('mail.reset_password_api_hint'))
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
