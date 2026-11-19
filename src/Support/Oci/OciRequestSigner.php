<?php

namespace App\Support\Oci;

use RuntimeException;

class OciRequestSigner
{
    /**
     * @return array<string, string>
     */
    public function sign(
        OciConfig $config,
        string $method,
        string $url,
        string $body,
        string $contentType = 'application/json',
    ): array {
        $method = strtoupper($method);
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false || ! isset($parsedUrl['host'])) {
            throw new RuntimeException('OCI request URL is invalid.');
        }

        $host = $parsedUrl['host'];
        $path = ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $contentSha256 = base64_encode(hash('sha256', $body, true));
        $contentLength = (string) strlen($body);

        $headers = [
            'date' => $date,
            'host' => $host,
            'content-length' => $contentLength,
            'content-type' => $contentType,
            'x-content-sha256' => $contentSha256,
        ];

        $signingHeaderNames = ['date', '(request-target)', 'host', 'content-length', 'content-type', 'x-content-sha256'];
        $signingString = $this->buildSigningString($method, $path, $headers, $signingHeaderNames);
        $signature = $this->signString($signingString, $config->privateKeyPath);
        $signingHeaderNamesString = implode(' ', $signingHeaderNames);

        $headers['authorization'] = sprintf(
            'Signature version="1",keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $config->keyId(),
            $signingHeaderNamesString,
            $signature,
        );

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<int, string>  $signingHeaderNames
     */
    public function buildSigningString(string $method, string $path, array $headers, array $signingHeaderNames): string
    {
        $lines = [];

        foreach ($signingHeaderNames as $headerName) {
            if ($headerName === '(request-target)') {
                $lines[] = '(request-target): '.strtolower($method).' '.$path;

                continue;
            }

            if (! array_key_exists($headerName, $headers)) {
                throw new RuntimeException("Signing header [{$headerName}] is missing.");
            }

            $lines[] = $headerName.': '.$headers[$headerName];
        }

        return implode("\n", $lines);
    }

    private function signString(string $signingString, string $privateKeyPath): string
    {
        $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));

        if ($privateKey === false) {
            throw new RuntimeException('OCI API private key could not be loaded.');
        }

        $signature = '';
        $signed = openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('OCI request signature could not be generated.');
        }

        return base64_encode($signature);
    }
}
