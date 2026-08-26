<?php

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\RedirectException;
use App\Helpers\Response;
use App\Providers\OfficeAccessTokenProvider;
use App\Services\Oidc\OidcSessionService;

final class AuthService
{
    public function __construct(
        private readonly OidcSessionService $sessions,
        private readonly OfficeAccessTokenProvider $tokens,
    ) {}

    public function isAuthenticated(): bool
    {
        if ($this->sessions->identity() === null) {
            return false;
        }

        try {
            $this->tokens->accessToken();

            return $this->sessions->identity() !== null;
        } catch (RedirectException) {
            return false;
        }
    }

    public function verify(bool $isObject = true): ?object
    {
        $identity = $this->sessions->identity();

        if ($identity === null) {
            $this->reject($isObject);
        }

        $this->tokens->accessToken();
        $identity = $this->sessions->identity();

        if ($identity === null) {
            $this->reject($isObject);
        }

        return (object) [
            'sub' => $identity['sub'],
            'user_uuid' => $identity['user_uuid'],
            'tenant_uuid' => $identity['tenant_uuid'],
            'email' => $identity['email'],
            'firstname' => $identity['given_name'],
            'lastname' => $identity['family_name'],
            'given_name' => $identity['given_name'],
            'family_name' => $identity['family_name'],
            'name' => $identity['name'],
            'initials' => $identity['initials'],
            'scopes' => $identity['scopes'],
        ];
    }

    public function logout(): void
    {
        $this->sessions->logout();
    }

    private function reject(bool $isObject): never
    {
        if (!$isObject) {
            throw new RedirectException('/auth/oidc/login?return_to=' . rawurlencode($this->currentPath()));
        }

        throw new HttpException(401, 'Authentification requise.', Response::INVALID);
    }

    private function currentPath(): string
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH) ?: '/dashboard');

        return str_starts_with($path, '/') ? $path : '/dashboard';
    }
}
