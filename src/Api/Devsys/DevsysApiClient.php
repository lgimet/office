<?php

namespace App\Api\Devsys;

final class DevsysApiClient
{
    private readonly string $baseUrl;
    private readonly string $token;
    private readonly int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) ($_ENV['DEVSYS_API_BASE_URL'] ?? ''), '/');
        $this->token = (string) ($_ENV['DEVSYS_API_TOKEN'] ?? '');
        $this->timeout = max(1, (int) ($_ENV['DEVSYS_API_TIMEOUT'] ?? 10));

        if ($this->baseUrl === '' || $this->token === '') {
            throw new DevsysApiException(
                'configuration',
                null,
                'La configuration de l’API Devsys est incomplète.'
            );
        }
    }

    public function get(string $path, array $query = []): array
    {
        $parameters = array_filter(
            $query,
            static fn(mixed $value): bool => $value !== null && $value !== ''
        );
        $url = $this->url($path);

        if ($parameters !== []) {
            $url .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->request('GET', $url);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $this->url($path), $payload);
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new DevsysApiException('network', null, 'Impossible d’initialiser l’appel API.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($payload !== null) {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_POSTFIELDS] = $encodedPayload;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $curlError = curl_error($handle);
        $curlErrorCode = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false) {
            $kind = $curlErrorCode === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network';

            throw new DevsysApiException(
                $kind,
                null,
                'L’API Devsys est temporairement indisponible : ' . $curlError
            );
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new DevsysApiException(
                'invalid_json',
                $status ?: null,
                'L’API Devsys a retourné une réponse non JSON.',
                $exception
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new DevsysApiException(
                $status === 401 || $status === 403 ? 'authentication' : 'http',
                $status,
                'L’API Devsys a retourné le statut HTTP ' . $status . '.'
            );
        }

        if (!is_array($decoded)) {
            throw new DevsysApiException('invalid_response', $status, 'La réponse API est invalide.');
        }

        return $decoded;
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
