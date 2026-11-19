<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages per-profile .env snapshots (.env.local, .env.staging, .env.production)
 * and the active profile marker (.env.profile). Laravel only reads `.env`.
 */
class EnvironmentProfileManager
{
    /** @var array<int, string> */
    private const PROFILES = ['local', 'staging', 'production'];

    /** @var array<string, string> */
    private const TEMPLATES = [
        'local' => '.env.local.example',
        'staging' => '.env.staging.example',
        'production' => '.env.production.example',
    ];

    public function __construct(
        private readonly EnvironmentKeyWriter $keyWriter,
        private readonly EnvironmentMutationGuard $guard,
        private readonly ?string $basePath = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function profiles(): array
    {
        return self::PROFILES;
    }

    public function isValidProfile(string $profile): bool
    {
        return in_array($profile, self::PROFILES, true);
    }

    public function assertValidProfile(string $profile): void
    {
        if (! $this->isValidProfile($profile)) {
            throw new InvalidArgumentException(
                "Invalid profile [{$profile}]. Choose: ".implode(', ', self::PROFILES).'.',
            );
        }
    }

    public function envPath(): string
    {
        return $this->resolvePath('.env');
    }

    public function markerPath(): string
    {
        return $this->resolvePath('.env.profile');
    }

    public function profilePath(string $profile): string
    {
        $this->assertValidProfile($profile);

        return $this->resolvePath('.env.'.$profile);
    }

    public function templatePath(string $profile): string
    {
        $this->assertValidProfile($profile);

        return $this->resolvePath(self::TEMPLATES[$profile]);
    }

    public function activeProfile(): ?string
    {
        if (File::exists($this->markerPath())) {
            $profile = trim(File::get($this->markerPath()));

            if ($this->isValidProfile($profile)) {
                return $profile;
            }
        }

        return $this->inferProfileFromEnv();
    }

    public function setActiveProfile(string $profile): void
    {
        $this->assertValidProfile($profile);
        File::put($this->markerPath(), $profile.PHP_EOL);
    }

    public function snapshotExists(string $profile): bool
    {
        return File::exists($this->profilePath($profile));
    }

    public function saveSnapshot(string $profile, ?string $sourcePath = null): void
    {
        $this->assertValidProfile($profile);

        $sourcePath ??= $this->envPath();

        if (! File::exists($sourcePath)) {
            throw new RuntimeException("Cannot save snapshot — source file not found: {$sourcePath}");
        }

        File::copy($sourcePath, $this->profilePath($profile));
    }

    public function bootstrapProfile(string $profile): void
    {
        $this->assertValidProfile($profile);

        $templatePath = $this->templatePath($profile);

        if (! File::exists($templatePath)) {
            throw new RuntimeException('Profile template not found: '.self::TEMPLATES[$profile]);
        }

        File::copy($templatePath, $this->profilePath($profile));
        File::copy($this->profilePath($profile), $this->envPath());
        $this->generateSecrets($this->envPath());
        $this->saveSnapshot($profile);
    }

    /**
     * @return array<int, string> Non-fatal warnings (e.g. APP_KEY generation failed).
     */
    public function installFromTemplate(string $profile, bool $forcePastBackup = false): array
    {
        $this->assertValidProfile($profile);

        return $this->guard->run(
            'env:install',
            fn (): array => $this->performInstallFromTemplate($profile),
            $this->installBackupPaths($profile),
            $forcePastBackup,
        );
    }

    public function activateProfile(string $profile, bool $forcePastBackup = false): EnvironmentSwitchResult
    {
        $this->assertValidProfile($profile);

        $current = $this->activeProfile();

        if ($current === $profile && File::exists($this->envPath()) && $this->snapshotExists($profile)) {
            return new EnvironmentSwitchResult(
                previousProfile: $current,
                activatedProfile: $profile,
                bootstrapped: false,
                skipped: true,
            );
        }

        return $this->guard->run(
            'env:switch',
            fn (): EnvironmentSwitchResult => $this->performActivateProfile($profile, $current),
            $this->switchBackupPaths(),
            $forcePastBackup,
        );
    }

    /**
     * @return array<int, array{profile: string, active: bool, snapshot: bool}>
     */
    public function profileStatuses(): array
    {
        $active = $this->activeProfile();

        return array_map(
            fn (string $profile): array => [
                'profile' => $profile,
                'active' => $active === $profile,
                'snapshot' => $this->snapshotExists($profile),
            ],
            self::PROFILES,
        );
    }

    /**
     * @return array<string, string>
     */
    private function installBackupPaths(string $profile): array
    {
        $paths = [];

        if (File::exists($this->envPath())) {
            $paths[$this->envPath()] = '.env';
        }

        if ($this->snapshotExists($profile)) {
            $paths[$this->profilePath($profile)] = '.env.'.$profile;
        }

        return $paths;
    }

    /**
     * @return array<string, string>
     */
    private function switchBackupPaths(): array
    {
        if (! File::exists($this->envPath())) {
            return [];
        }

        return [$this->envPath() => '.env'];
    }

    /**
     * @return array<int, string>
     */
    private function performInstallFromTemplate(string $profile): array
    {
        $templatePath = $this->templatePath($profile);

        if (! File::exists($templatePath)) {
            throw new RuntimeException('Profile template not found: '.self::TEMPLATES[$profile]);
        }

        File::copy($templatePath, $this->envPath());
        $warnings = $this->generateSecrets($this->envPath());
        $this->saveSnapshot($profile);
        $this->setActiveProfile($profile);

        return $warnings;
    }

    private function performActivateProfile(string $profile, ?string $current): EnvironmentSwitchResult
    {
        $savedProfile = null;

        if (File::exists($this->envPath())) {
            $savedProfile = $current ?? $this->inferProfileFromEnv() ?? 'local';
            $this->saveSnapshot($savedProfile);
        }

        $bootstrapped = false;

        if (! $this->snapshotExists($profile)) {
            $this->bootstrapProfile($profile);
            $bootstrapped = true;
        } else {
            File::copy($this->profilePath($profile), $this->envPath());
            $this->fillEmptySecrets($this->envPath());
            $this->saveSnapshot($profile);
        }

        $this->setActiveProfile($profile);

        return new EnvironmentSwitchResult(
            previousProfile: $savedProfile,
            activatedProfile: $profile,
            bootstrapped: $bootstrapped,
            skipped: false,
        );
    }

    private function inferProfileFromEnv(): ?string
    {
        if (! File::exists($this->envPath())) {
            return null;
        }

        $contents = File::get($this->envPath());

        if (! preg_match('/^APP_ENV=(.*)$/m', $contents, $matches)) {
            return null;
        }

        $appEnv = trim($matches[1], " \t\n\r\0\x0B\"'");

        return $this->isValidProfile($appEnv) ? $appEnv : null;
    }

    /**
     * @return array<int, string>
     */
    private function generateSecrets(string $envPath): array
    {
        $warnings = [];

        try {
            $this->keyWriter->writeAppKey($envPath);
        } catch (RuntimeException $exception) {
            $warnings[] = 'Could not generate APP_KEY: '.$exception->getMessage();
        }

        $this->fillEmptySecrets($envPath);

        return $warnings;
    }

    private function fillEmptySecrets(string $envPath): void
    {
        if ($this->jwtSecretIsEmpty($envPath)) {
            $this->writeJwtSecret($envPath);
        }

        $this->keyWriter->writeBlindIndexPepperIfEmpty($envPath);
    }

    private function jwtSecretIsEmpty(string $envPath): bool
    {
        $contents = File::get($envPath);

        if (! preg_match('/^JWT_SECRET=(.*)$/m', $contents, $matches)) {
            return true;
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'") === '';
    }

    private function writeJwtSecret(string $envPath): void
    {
        $secret = bin2hex(random_bytes(32));
        $contents = File::get($envPath);

        if (preg_match('/^JWT_SECRET=.*$/m', $contents)) {
            $updated = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET="'.$secret.'"', $contents);
        } else {
            $updated = rtrim($contents).PHP_EOL.'JWT_SECRET="'.$secret.'"'.PHP_EOL;
        }

        File::put($envPath, $updated);
    }

    private function resolvePath(string $relative): string
    {
        $root = $this->basePath ?? base_path();

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }
}
