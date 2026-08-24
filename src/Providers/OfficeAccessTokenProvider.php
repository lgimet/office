<?php
namespace App\Providers;
use App\Core\Exceptions\RedirectException;
use App\Services\Oidc\OidcSessionService;
final class OfficeAccessTokenProvider
{
    public function __construct(private readonly OidcSessionService $sessions) {}
    public function accessToken(): string { try{return $this->sessions->accessToken();}catch(\Throwable){throw new RedirectException('/auth/oidc/login?return_to='.rawurlencode((string)(parse_url($_SERVER['REQUEST_URI']??'/dashboard',PHP_URL_PATH)?:'/dashboard')));} }
}
