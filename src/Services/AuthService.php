<?php
namespace App\Services;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\RedirectException;
use App\Helpers\Response;
use App\Services\Oidc\OidcSessionService;
final class AuthService
{
    public function __construct(private readonly OidcSessionService $sessions) {}
    public function isAuthenticated(): bool { return $this->sessions->isAuthenticated(); }
    public function verify(bool $isObject = true): ?object {
        $identity = $this->sessions->identity();
        if (!$this->sessions->isAuthenticated() || $identity === null) { if (!$isObject) throw new RedirectException('/auth/oidc/login?return_to=' . rawurlencode($this->currentPath())); throw new HttpException(401, 'Authentification requise.', Response::INVALID); }
        return (object) ['sub'=>$identity['sub'],'user_uuid'=>$identity['user_uuid'],'tenant_uuid'=>$identity['tenant_uuid'],'email'=>$identity['email'],'firstname'=>$identity['given_name'],'lastname'=>$identity['family_name'],'given_name'=>$identity['given_name'],'family_name'=>$identity['family_name'],'name'=>$identity['name'],'initials'=>$identity['initials'],'scopes'=>$identity['scopes']];
    }
    public function logout(): void { $this->sessions->logout(); throw new RedirectException('/'); }
    private function currentPath(): string { $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH) ?: '/dashboard'); return str_starts_with($path, '/') ? $path : '/dashboard'; }
}
