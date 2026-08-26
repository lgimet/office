<?php

namespace App\Services\Oidc;

class OidcSessionService
{
    public const MARGIN = 30;
    public function identity(): ?array { return is_array($_SESSION['office_identity'] ?? null) ? $_SESSION['office_identity'] : null; }
    public function accessToken(): string
    {
        $oauth = $_SESSION['office_oauth'] ?? null;
        if (!is_array($oauth) || !is_string($oauth['access_token'] ?? null) || (int) ($oauth['expires_at'] ?? 0) <= time() + self::MARGIN) throw new OidcSessionExpiredException('La session OIDC a expiré.');
        return $oauth['access_token'];
    }
    public function isAuthenticated(): bool { try { $this->accessToken(); return $this->identity() !== null; } catch (OidcException) { return false; } }
    public function create(object $claims, array $userinfo, int $expiresIn, array $scopes): void
    {
        if (($userinfo['sub'] ?? null) !== ($claims->sub ?? null) || ($userinfo['tenant_id'] ?? null) !== ($claims->tenant_id ?? null) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string)($userinfo['tenant_id'] ?? ''))) throw new OidcIdentityException('Identité UserInfo incohérente.');
        session_regenerate_id(true);
        $given = (string) ($userinfo['given_name'] ?? ''); $family = (string) ($userinfo['family_name'] ?? ''); $name = (string) ($userinfo['name'] ?? trim($given . ' ' . $family));
        $_SESSION['office_identity'] = ['sub' => $claims->sub, 'user_uuid' => substr($claims->sub, 5), 'tenant_uuid' => $claims->tenant_id, 'email' => (string) ($userinfo['email'] ?? ''), 'given_name' => $given, 'family_name' => $family, 'name' => $name, 'initials' => $this->initials($given, $family, $name, (string) ($userinfo['email'] ?? '')), 'scopes' => $scopes, 'authenticated_at' => time()];
    }
    public function storeAccessToken(string $token, int $expiresIn): void { $_SESSION['office_oauth'] = ['access_token' => $token, 'expires_at' => time() + $expiresIn]; }
    public function logout(): void
    {
        $params = session_get_cookie_params();
        $name = session_name();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        $options = ['expires'=>time()-3600,'path'=>(string)($params['path']??'/'),'secure'=>(bool)($params['secure']??false),'httponly'=>(bool)($params['httponly']??true),'samesite'=>(string)($params['samesite']??'Lax')];
        if (isset($params['domain']) && $params['domain'] !== '') $options['domain'] = $params['domain'];
        setcookie($name, '', $options);
    }
    private function initials(string $given, string $family, string $name, string $email): string { $parts = preg_split('/\s+/', trim($given . ' ' . $family)) ?: []; if (count($parts) >= 2) return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts)-1], 0, 1)); $fallback = trim($name ?: $email); return strtoupper(mb_substr($fallback, 0, 2)); }
}
