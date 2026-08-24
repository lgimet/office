<?php

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\RedirectException;
use App\Helpers\Response;
use App\Repositories\AuthRepository;
use App\Repositories\UserRepository;
use Firebase\JWT\ExpiredException;

class AuthService
{
    private const ACCESS_COOKIE = 'auth_token';
    private const REFRESH_COOKIE = 'refresh_token';

    private ?object $user = null;
    private AuthRepository $repo;
    private UserRepository $userRepository;
    private JWTService $jwt;
    private int $accessExpiration;
    private int $refreshExpiration;

    public function __construct(
        AuthRepository $repo,
        UserRepository $userRepository,
        JWTService $jwt
    ) {
        $this->repo = $repo;
        $this->userRepository = $userRepository;
        $this->jwt = $jwt;
        $this->accessExpiration = (int) ($_ENV['JWT_EXPIRATION'] ?? 3600);
        $this->refreshExpiration = (int) ($_ENV['JWT_REFRESH_EXPIRATION'] ?? 1209600);
    }

    public function login(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $user = $this->repo->getByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $newPasswordHash = password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)
            ? password_hash($password, PASSWORD_DEFAULT)
            : null;
        $this->repo->completeSuccessfulLogin((int) $user['id'], $newPasswordHash);

        $this->issueSessionTokens($this->buildUserPayload($user));

        return true;
    }

    public function isAuthenticated(): bool
    {
        $user = $this->resolveAuthenticatedUser();

        if ($user === null) {
            return false;
        }

        // Une session antérieure peut encore porter un cookie limité à Office.
        // Lors du passage par /login, on republie le même jeton d'accès sur le
        // domaine partagé sans créer de session parallèle.
        $accessToken = $_COOKIE[self::ACCESS_COOKIE] ?? null;
        if (is_string($accessToken) && $accessToken !== '') {
            setcookie(
                self::ACCESS_COOKIE,
                $accessToken,
                $this->getCookieOptions(time() + $this->accessExpiration, true)
            );
        }

        return true;
    }

    public function verify(bool $isObject = true): ?object
    {
        $decoded = $this->resolveAuthenticatedUser($isObject);

        if ($decoded !== null) {
            return $decoded;
        }

        throw new HttpException(401, 'Token invalide', Response::INVALID);
    }

    public function logout(): void
    {
        $userId = $_SESSION['user']->user_id ?? $this->user->user_id ?? null;

        if ($userId !== null) {
            $this->userRepository->clearRefreshTokenHash((int) $userId);
        }

        $this->expireCookie(self::ACCESS_COOKIE);
        $this->expireCookie(self::ACCESS_COOKIE, true);
        $this->expireCookie(self::REFRESH_COOKIE);

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            session_regenerate_id(true);
        }

        $this->user = null;

        throw new RedirectException('/');
    }

    private function resolveAuthenticatedUser(bool $isObject = true): ?object
    {
        try {
            $token = $_COOKIE[self::ACCESS_COOKIE] ?? null;

            if ($token) {
                $decoded = $this->jwt->validate($token);

                if (($decoded->typ ?? null) !== 'access') {
                    return $this->restoreFromRefreshToken();
                }

                $_SESSION['user'] = $decoded;
                $this->user = $decoded;
                return $decoded;
            }

            return $this->restoreFromRefreshToken();
        } catch (ExpiredException $e) {
            $restored = $this->restoreFromRefreshToken();
            if ($restored !== null) {
                return $restored;
            }

            if (!$isObject) {
                throw new RedirectException('/');
            }
            throw new HttpException(401, 'Token expiré', Response::EXPIRED, $e);
        } catch (\Throwable) {
            $restored = $this->restoreFromRefreshToken();
            if ($restored !== null) {
                return $restored;
            }

            if (!$isObject) {
                throw new RedirectException('/');
            }
            throw new HttpException(401, 'Token invalide', Response::INVALID);
        }
    }

    private function restoreFromRefreshToken(): ?object
    {
        $refreshToken = $_COOKIE[self::REFRESH_COOKIE] ?? null;
        if (!$refreshToken) {
            return null;
        }

        try {
            $decodedRefresh = $this->jwt->validate($refreshToken);

            if (($decodedRefresh->typ ?? null) !== 'refresh') {
                $this->invalidateSession();
                return null;
            }

            $userId = (int) ($decodedRefresh->user_id ?? 0);
            if (
                $userId <= 0
                || !$this->userRepository->hasValidRefreshTokenHash($userId, hash('sha256', $refreshToken))
            ) {
                $this->invalidateSession();
                return null;
            }

            $user = $this->repo->getIdentityById($userId);
            if ($user === null) {
                $this->invalidateSession();
                return null;
            }

            $this->issueSessionTokens($this->buildUserPayload($user));
            return $this->user;
        } catch (\Throwable) {
            $this->invalidateSession();
            return null;
        }
    }

    private function issueSessionTokens(array $payload): void
    {
        $userId = (int) $payload['user_id'];
        session_regenerate_id(true);
        $accessToken = $this->jwt->generateAccessToken($payload);
        $refreshToken = $this->jwt->generateRefreshToken([
            'user_id' => $userId,
        ]);

        $this->userRepository->storeRefreshTokenHash(
            $userId,
            hash('sha256', $refreshToken),
            $this->refreshExpiration
        );

        // Supprime l'ancien cookie limité à Office avant de définir le cookie
        // partagé. Cela évite deux cookies auth_token de portées différentes.
        $this->expireCookie(self::ACCESS_COOKIE);
        $this->expireCookie(self::ACCESS_COOKIE, true);
        setcookie(
            self::ACCESS_COOKIE,
            $accessToken,
            $this->getCookieOptions(time() + $this->accessExpiration, true)
        );
        setcookie(
            self::REFRESH_COOKIE,
            $refreshToken,
            $this->getCookieOptions(time() + $this->refreshExpiration)
        );

        $decodedAccess = $this->jwt->validate($accessToken);
        $_SESSION['user'] = $decodedAccess;
        $this->user = $decodedAccess;
    }

    private function buildUserPayload(array $user): array
    {
        return [
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'lastname' => $user['lastname'],
            'firstname' => $user['firstname'],
            'initials' => $user['initials'],
        ];
    }

    private function invalidateSession(): void
    {
        $userId = $_SESSION['user']->user_id ?? $this->user->user_id ?? null;
        if ($userId !== null) {
            $this->userRepository->clearRefreshTokenHash((int) $userId);
        }

        $this->expireCookie(self::ACCESS_COOKIE);
        $this->expireCookie(self::ACCESS_COOKIE, true);
        $this->expireCookie(self::REFRESH_COOKIE);
        unset($_SESSION['user']);
        $this->user = null;
    }

    private function getCookieOptions(int $expires, bool $sharedAcrossDevsysSubdomains = false): array
    {
        $options = [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        $domain = trim((string) ($_ENV['AUTH_COOKIE_DOMAIN'] ?? ''));
        if ($sharedAcrossDevsysSubdomains && $domain !== '') {
            $options['domain'] = $domain;
        }

        return $options;
    }

    private function expireCookie(string $name, bool $sharedAcrossDevsysSubdomains = false): void
    {
        setcookie(
            $name,
            '',
            $this->getCookieOptions(time() - 3600, $sharedAcrossDevsysSubdomains)
        );
    }
}
