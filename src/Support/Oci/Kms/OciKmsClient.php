<?php

namespace App\Support\Oci\Kms;

use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\OciConfigLoader;
use App\Support\Oci\OciRequestSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class OciKmsClient implements KmsDekClientInterface
{
    private const GENERATE_DEK_PATH = '/20180608/generateDataEncryptionKey';

    private const DECRYPT_PATH = '/20180608/decrypt';

    public function __construct(
        private readonly OciConfigLoader $configLoader = new OciConfigLoader,
        private readonly OciRequestSigner $signer = new OciRequestSigner,
    ) {}

    public function verifyConnection(): OciKmsConnectionResult
    {
        $configurationError = $this->configLoader->configurationError();

        if ($configurationError !== null) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Configuration,
                $configurationError['message'],
            );
        }

        $cryptoEndpoint = rtrim((string) config('oci.kms_crypto_endpoint'), '/');
        $url = $cryptoEndpoint.self::GENERATE_DEK_PATH;
        $body = json_encode([
            'keyId' => (string) config('oci.kms_key_id'),
            'keyShape' => [
                'algorithm' => 'AES',
                'length' => 32,
            ],
            'includePlaintextKey' => false,
        ], JSON_THROW_ON_ERROR);

        $response = $this->postCrypto($url, $body);

        if ($response instanceof OciKmsConnectionResult) {
            return $response;
        }

        if ($response->successful()) {
            $payload = $response->json();

            if (is_array($payload) && (isset($payload['ciphertext']) || isset($payload['id']))) {
                return OciKmsConnectionResult::success();
            }

            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Unknown,
                'OCI KMS responded successfully but the payload did not contain expected fields.',
                httpStatus: $response->status(),
            );
        }

        return $this->categorizeHttpFailure($response->status(), $response->json());
    }

    public function generateDek(): OciKmsDekResult
    {
        $configurationError = $this->configLoader->configurationError();

        if ($configurationError !== null) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Configuration,
                $configurationError['message'],
            );
        }

        $cryptoEndpoint = rtrim((string) config('oci.kms_crypto_endpoint'), '/');
        $url = $cryptoEndpoint.self::GENERATE_DEK_PATH;
        $body = json_encode([
            'keyId' => (string) config('oci.kms_key_id'),
            'keyShape' => [
                'algorithm' => 'AES',
                'length' => 32,
            ],
            'includePlaintextKey' => true,
        ], JSON_THROW_ON_ERROR);

        $response = $this->postCrypto($url, $body);

        if ($response instanceof OciKmsConnectionResult) {
            return OciKmsDekResult::failure(
                $response->category ?? OciKmsFailureCategory::Unknown,
                (string) $response->message,
                $response->ociCode,
                $response->httpStatus,
            );
        }

        if (! $response->successful()) {
            $failure = $this->categorizeHttpFailure($response->status(), $response->json());

            return OciKmsDekResult::failure(
                $failure->category ?? OciKmsFailureCategory::Unknown,
                (string) $failure->message,
                $failure->ociCode,
                $failure->httpStatus,
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['ciphertext'], $payload['plaintext'])) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Unknown,
                'OCI KMS generateDataEncryptionKey response was missing ciphertext or plaintext.',
                httpStatus: $response->status(),
            );
        }

        $plaintextDek = base64_decode((string) $payload['plaintext'], true);
        $wrappedDek = (string) $payload['ciphertext'];

        if ($plaintextDek === false || strlen($plaintextDek) !== 32) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Unknown,
                'OCI KMS returned an invalid plaintext DEK.',
                httpStatus: $response->status(),
            );
        }

        return OciKmsDekResult::success($plaintextDek, $wrappedDek);
    }

    public function decryptDek(string $wrappedDek): OciKmsDekResult
    {
        $configurationError = $this->configLoader->configurationError();

        if ($configurationError !== null) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Configuration,
                $configurationError['message'],
            );
        }

        $cryptoEndpoint = rtrim((string) config('oci.kms_crypto_endpoint'), '/');
        $url = $cryptoEndpoint.self::DECRYPT_PATH;
        $body = json_encode([
            'keyId' => (string) config('oci.kms_key_id'),
            'ciphertext' => $wrappedDek,
        ], JSON_THROW_ON_ERROR);

        $response = $this->postCrypto($url, $body);

        if ($response instanceof OciKmsConnectionResult) {
            return OciKmsDekResult::failure(
                $response->category ?? OciKmsFailureCategory::Unknown,
                (string) $response->message,
                $response->ociCode,
                $response->httpStatus,
            );
        }

        if (! $response->successful()) {
            $failure = $this->categorizeHttpFailure($response->status(), $response->json());

            return OciKmsDekResult::failure(
                $failure->category ?? OciKmsFailureCategory::Unknown,
                (string) $failure->message,
                $failure->ociCode,
                $failure->httpStatus,
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['plaintext'])) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Unknown,
                'OCI KMS decrypt response was missing plaintext.',
                httpStatus: $response->status(),
            );
        }

        $plaintextDek = base64_decode((string) $payload['plaintext'], true);

        if ($plaintextDek === false || strlen($plaintextDek) !== 32) {
            return OciKmsDekResult::failure(
                OciKmsFailureCategory::Unknown,
                'OCI KMS returned an invalid plaintext DEK.',
                httpStatus: $response->status(),
            );
        }

        return OciKmsDekResult::success($plaintextDek, $wrappedDek);
    }

    private function postCrypto(string $url, string $body): Response|OciKmsConnectionResult
    {
        try {
            $ociConfig = $this->configLoader->load();
        } catch (Throwable $exception) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Configuration,
                $exception->getMessage(),
            );
        }

        try {
            $signedHeaders = $this->signer->sign($ociConfig, 'POST', $url, $body);
        } catch (Throwable $exception) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Configuration,
                $exception->getMessage(),
            );
        }

        try {
            return Http::timeout((int) config('oci.http_timeout', 15))
                ->withHeaders($signedHeaders)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Network,
                'Could not reach the OCI KMS crypto endpoint: '.$exception->getMessage(),
            );
        } catch (Throwable $exception) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Unknown,
                $exception->getMessage(),
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function categorizeHttpFailure(int $status, ?array $payload): OciKmsConnectionResult
    {
        $ociCode = is_array($payload) ? (string) ($payload['code'] ?? '') : '';
        $ociMessage = is_array($payload) ? (string) ($payload['message'] ?? '') : '';

        $message = $ociMessage !== '' ? $ociMessage : 'OCI KMS request failed with HTTP '.$status.'.';

        if ($status === 401 || $ociCode === 'NotAuthenticated') {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Authentication,
                $this->authenticationMessage($message),
                $ociCode !== '' ? $ociCode : null,
                $status,
            );
        }

        if ($status === 403 || in_array($ociCode, ['NotAuthorizedOrNotFound', 'Forbidden'], true)) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Authorization,
                $this->authorizationMessage($message),
                $ociCode !== '' ? $ociCode : null,
                $status,
            );
        }

        if ($status === 404 || $ociCode === 'NotFound') {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::NotFound,
                $this->notFoundMessage($message),
                $ociCode !== '' ? $ociCode : null,
                $status,
            );
        }

        if ($status >= 500) {
            return OciKmsConnectionResult::failure(
                OciKmsFailureCategory::Unknown,
                $message,
                $ociCode !== '' ? $ociCode : null,
                $status,
            );
        }

        return OciKmsConnectionResult::failure(
            OciKmsFailureCategory::Unknown,
            $message,
            $ociCode !== '' ? $ociCode : null,
            $status,
        );
    }

    private function authenticationMessage(string $message): string
    {
        return $message.' Check the API key fingerprint, private key PEM, and that the configured OCI profile matches the uploaded public key.';
    }

    private function authorizationMessage(string $message): string
    {
        return $message.' This usually means IAM policy is missing. Grant the API user permission to use the master encryption key, for example: allow group <group> to use keys in compartment <compartment> where target.key.id = \'<key-ocid>\'. Stop here and review IAM together instead of retrying in a loop.';
    }

    private function notFoundMessage(string $message): string
    {
        return $message.' Verify OCI_KMS_KEY_ID and OCI_KMS_CRYPTO_ENDPOINT belong to the same vault and region.';
    }
}
