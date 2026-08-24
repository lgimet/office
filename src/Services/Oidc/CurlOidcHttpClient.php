<?php

namespace App\Services\Oidc;

final class CurlOidcHttpClient implements OidcHttpClientInterface
{
    public function __construct(
        private readonly float $timeout = 10.0,
        private readonly float $connectTimeout = 5.0,
    ) {}

    public function getJson(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function postForm(string $url, array $fields, ?array $basicAuth = null): array
    {
        $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
        if ($basicAuth !== null) {
            $headers[] = 'Authorization: Basic ' . base64_encode($basicAuth[0] . ':' . $basicAuth[1]);
        }
        return $this->request('POST', $url, http_build_query($fields, '', '&', PHP_QUERY_RFC3986), $headers);
    }

    public function getBearer(string $url, string $token): array
    {
        return $this->request('GET', $url, null, ['Accept: application/json', 'Authorization: Bearer ' . $token]);
    }

    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new OidcTransportException('Impossible d’initialiser le client OIDC.');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->connectTimeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false || $error !== '') {
            throw new OidcTransportException('Le fournisseur OIDC est indisponible.');
        }
        $json = json_decode(substr($raw, $headerSize), true);
        if (!is_array($json)) {
            throw new OidcProtocolException('La réponse JSON OIDC est invalide.');
        }
        return ['status' => $status, 'body' => $json, 'headers' => []];
    }
}
