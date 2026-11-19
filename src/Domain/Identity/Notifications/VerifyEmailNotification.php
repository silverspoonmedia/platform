<?php

namespace App\Domain\Identity\Notifications;

use App\Support\SpaPublicUrl;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Sign the API verify route (HMAC is bound to APP_URL). When SPA_URL is
     * set, the browser lands on the SPA page and the Worker forwards the same
     * expires/signature query to that API route.
     *
     * @param  mixed  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        $id = (string) $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        $signed = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $id,
                'hash' => $hash,
            ],
        );

        return SpaPublicUrl::emailVerification($id, $hash, $signed);
    }
}
