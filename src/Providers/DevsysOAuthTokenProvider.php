<?php

declare(strict_types=1);

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class DevsysOAuthTokenProvider
{
    private const EXPIRY_MARGIN_SECONDS = 30;

    public function __construct(
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $scope,
        private readonly float $timeout,
        private readonly float $connectTimeout,
    ) {
    }

    public function accessToken(): string
    {
        $cacheFile = $this->cacheFile();

        $handle = @fopen($cacheFile, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Impossible de verrouiller le cache du jeton Devsys.');
        }

        try {
            $cachedToken = $this->readCachedToken($handle);

            if ($cachedToken !== null) {
                return $cachedToken;
            }

            $token = $this->requestToken();
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($token, JSON_THROW_ON_ERROR));
            fflush($handle);
            chmod($cacheFile, 0600);

            return $token['access_token'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readCachedToken($handle): ?string
    {
        rewind($handle);
        $contents = stream_get_contents($handle);

        if (!is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $cached = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($cached)
            || !is_string($cached['access_token'] ?? null)
            || !is_int($cached['expires_at'] ?? null)
            || $cached['expires_at'] <= time() + self::EXPIRY_MARGIN_SECONDS
        ) {
            return null;
        }

        return $cached['access_token'];
    }

    /** @return array{access_token: string, expires_at: int} */
    private function requestToken(): array
    {
        $client = new Client([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors' => false,
        ]);

        try {
            $response = $client->post($this->tokenUrl, [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'scope' => $this->scope,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('Impossible d’obtenir un jeton OAuth Devsys.', 0, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('L’authentification OAuth Devsys a échoué.');
        }

        try {
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('La réponse OAuth Devsys est invalide.', 0, $exception);
        }

        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '' || !is_int($expiresIn) || $expiresIn <= 0) {
            throw new \RuntimeException('La réponse OAuth Devsys ne contient pas de jeton valide.');
        }

        return [
            'access_token' => $accessToken,
            'expires_at' => time() + $expiresIn,
        ];
    }

    private function cacheFile(): string
    {
        $identifier = hash('sha256', $this->clientId . '|' . $this->scope);

        foreach ([
            dirname(__DIR__, 2) . '/var/cache',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/office-devsys',
        ] as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                continue;
            }

            if (is_writable($directory)) {
                return $directory . '/devsys-oauth-' . $identifier . '.json';
            }
        }

        throw new \RuntimeException('Impossible de préparer le cache du jeton Devsys.');
    }
}
