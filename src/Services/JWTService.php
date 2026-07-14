<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private string $secret;
    private int $accessExpiration;
    private int $refreshExpiration;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'];
        $this->accessExpiration = (int) ($_ENV['JWT_EXPIRATION'] ?? 3600);
        $this->refreshExpiration = (int) ($_ENV['JWT_REFRESH_EXPIRATION'] ?? 1209600);
    }

    public function generateAccessToken(array $payload): string
    {
        return $this->generate($payload, $this->accessExpiration, 'access');
    }

    public function generateRefreshToken(array $payload): string
    {
        return $this->generate($payload, $this->refreshExpiration, 'refresh');
    }

    public function generate(array $payload, int $expiresIn, ?string $type = null): string
    {
        $issuedAt = time();
        $payload['iat'] = $payload['iat'] ?? $issuedAt;
        $payload['exp'] = $issuedAt + $expiresIn;

        if ($type !== null) {
            $payload['typ'] = $type;
        }

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validate(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
