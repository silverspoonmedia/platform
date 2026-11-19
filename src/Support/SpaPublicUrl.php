<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * Browser-facing URLs for the Vue-Workers SPA. Paths are origin-prefixed using
 * {@see config('services.spa.url')} when set, otherwise {@see config('app.url')}.
 * Never mint a foreign host.
 */
final class SpaPublicUrl
{
    public static function origin(): ?string
    {
        $origin = rtrim((string) config('services.spa.url'), '/');

        return $origin === '' ? null : $origin;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public static function to(string $path, array $query = []): string
    {
        $normalized = self::normalizePath($path);
        $origin = self::origin() ?? rtrim((string) config('app.url'), '/');
        $queryString = self::queryString($query);

        return $origin.$normalized.$queryString;
    }

    public static function passwordReset(string $token, string $email): string
    {
        // The Blade `password.reset` screen is gone; the SPA owns the form.
        // Always emit the SPA query shape regardless of configured origin.
        return self::to('/reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public static function emailVerification(string $id, string $hash, string $signedApiUrl): string
    {
        if (self::origin() === null) {
            return $signedApiUrl;
        }

        $query = parse_url($signedApiUrl, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);

        $forward = [];
        foreach (['expires', 'signature'] as $key) {
            if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '') {
                $forward[$key] = $params[$key];
            }
        }

        return self::to('/verify-email/'.self::assertSafeSegment($id).'/'.self::assertSafeSegment($hash), $forward);
    }

    public static function publicFileShare(string $fileId): string
    {
        if (self::origin() === null) {
            return route('archivd.public.files.show', ['file' => $fileId]);
        }

        return self::to('/s/f/'.self::assertSafeSegment($fileId));
    }

    public static function publicFolderShare(string $folderId): string
    {
        if (self::origin() === null) {
            return route('archivd.public.folders.show', ['folder' => $folderId]);
        }

        return self::to('/s/d/'.self::assertSafeSegment($folderId));
    }

    public static function paymentReturn(): string
    {
        if (self::origin() !== null) {
            return self::to('/payment/return');
        }

        if (Route::has('payment.ipaymu.return')) {
            return route('payment.ipaymu.return', [], true);
        }

        return rtrim((string) config('app.url'), '/').'/payment/ipaymu/return';
    }

    public static function paymentCancel(): string
    {
        if (self::origin() !== null) {
            return self::to('/payment/cancel');
        }

        if (Route::has('payment.ipaymu.cancel')) {
            return route('payment.ipaymu.cancel', [], true);
        }

        return rtrim((string) config('app.url'), '/').'/payment/ipaymu/cancel';
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '://') || str_contains($path, '\\') || str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException('Invalid SPA public URL path.');
        }

        $cut = strpos($path, '?');
        $pathname = $cut === false ? $path : substr($path, 0, $cut);
        if ($pathname === false || $pathname === '') {
            throw new InvalidArgumentException('Invalid SPA public URL path.');
        }

        return $pathname;
    }

    private static function assertSafeSegment(string $segment): string
    {
        if ($segment === '' || str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, '..') || str_contains($segment, ':') || str_contains($segment, '?') || str_contains($segment, '#')) {
            throw new InvalidArgumentException('Invalid SPA public URL segment.');
        }

        return $segment;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private static function queryString(array $query): string
    {
        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        if ($filtered === []) {
            return '';
        }

        return '?'.http_build_query($filtered);
    }
}
