<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Database\Factories\UserFactory;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Support\Encryption\Casts\EnvelopeEncrypted;
use App\Support\Localization\LocalePreference;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property string $guard_name
 */
#[Fillable(['name', 'email', 'locale', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasActivity, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected string $guard_name = 'api';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => EnvelopeEncrypted::class.':users,two_factor_secret,two_factor',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Locale the framework uses to render queued/mailed notifications, so a
     * recipient's email matches their saved preference — not whatever locale
     * the sending request happened to resolve (e.g. a guest's forgot-password
     * submission). Falls back to the app default when no preference is set.
     */
    public function preferredLocale(): ?string
    {
        $column = config('localization.user_column');

        if (! is_string($column) || $column === '') {
            return null;
        }

        $locale = $this->{$column} ?? null;

        return is_string($locale)
            ? LocalePreference::normalize($locale)
            : null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hasTwoFactorSecret(): bool
    {
        return filled($this->getRawOriginal('two_factor_secret'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'locale', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user');
    }
}
