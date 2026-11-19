<?php

namespace App\Support\Config;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

class ConfigSecretVerifier
{
    /**
     * @var list<string>
     */
    private const PLACEHOLDER_MARKERS = [
        'xxxxxxxx',
        'ocid1.key.ap-singapore-1.xxxxxxxxx',
    ];

    public function __construct(
        private readonly FreshConfigurationLoader $freshLoader,
    ) {}

    public function verify(?Repository $config = null): ConfigSecretVerificationResult
    {
        $config ??= config();

        return new ConfigSecretVerificationResult(
            checks: $this->buildChecks($config),
            fromFreshBootstrap: false,
        );
    }

    public function verifyFresh(Application $baseApplication): ConfigSecretVerificationResult
    {
        return new ConfigSecretVerificationResult(
            checks: $this->buildChecks($this->freshLoader->loadRepository($baseApplication)),
            fromFreshBootstrap: true,
        );
    }

    /**
     * @return list<ConfigSecretCheck>
     */
    private function buildChecks(Repository $config): array
    {
        $checks = [];

        $checks[] = $this->requiredString($config, 'app.key', 'APP_KEY');
        $checks[] = $this->requiredString($config, 'jwt.secret', 'JWT_SECRET');

        $checks[] = $this->requiredString(
            $config,
            'payment.gateways.ipaymu.api_key',
            'IPAYMU_API_KEY',
        );
        $checks[] = $this->requiredString(
            $config,
            'payment.gateways.ipaymu.va',
            'IPAYMU_VA_KEY',
        );

        $checks[] = $this->optionalString(
            $config,
            'payment.gateways.midtrans.server_key',
            'MIDTRANS_SERVER_KEY',
        );
        $checks[] = $this->optionalString(
            $config,
            'payment.gateways.midtrans.client_key',
            'MIDTRANS_CLIENT_KEY',
        );

        if ((bool) $config->get('encryption.kms_enabled')) {
            $checks[] = $this->requiredOciValue($config, 'oci.kms_key_id', 'OCI_KMS_KEY_ID');
            $checks[] = $this->requiredOciValue($config, 'oci.kms_crypto_endpoint', 'OCI_KMS_CRYPTO_ENDPOINT');
            $checks[] = $this->requiredString(
                $config,
                'encryption.blind_index_pepper',
                'ENCRYPTION_BLIND_INDEX_PEPPER',
            );
        }

        return $checks;
    }

    private function requiredString(Repository $config, string $key, string $label): ConfigSecretCheck
    {
        $value = $this->stringValue($config, $key);

        if ($value === '') {
            return new ConfigSecretCheck($key, $label, 'missing');
        }

        return new ConfigSecretCheck($key, $label, 'ok');
    }

    private function optionalString(Repository $config, string $key, string $label): ConfigSecretCheck
    {
        $value = $this->stringValue($config, $key);

        if ($value === '') {
            return new ConfigSecretCheck($key, $label, 'missing', 'warning');
        }

        return new ConfigSecretCheck($key, $label, 'ok', 'warning');
    }

    private function requiredOciValue(Repository $config, string $key, string $label): ConfigSecretCheck
    {
        $value = $this->stringValue($config, $key);

        if ($value === '') {
            return new ConfigSecretCheck($key, $label, 'missing');
        }

        if ($this->isPlaceholder($value)) {
            return new ConfigSecretCheck($key, $label, 'placeholder');
        }

        return new ConfigSecretCheck($key, $label, 'ok');
    }

    private function stringValue(Repository $config, string $key): string
    {
        $value = Arr::get($config->all(), $key);

        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function isPlaceholder(string $value): bool
    {
        $normalized = strtolower($value);

        foreach (self::PLACEHOLDER_MARKERS as $marker) {
            if (str_contains($normalized, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }
}
