<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Http;

class HcaptchaVerifier
{
    public function isEnabled(): bool
    {
        return (bool) setting_config('hcaptcha.enabled');
    }

    public function verify(string $response, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($response === '') {
            return false;
        }

        $secret = config('hcaptcha.secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $response,
        ];

        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $httpResponse = Http::asForm()
            ->timeout(5)
            ->post((string) config('hcaptcha.verify_url'), $payload);

        if (! $httpResponse->successful()) {
            return false;
        }

        return (bool) $httpResponse->json('success', false);
    }
}
