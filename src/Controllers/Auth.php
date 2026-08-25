<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Services\Oidc\OidcConfig;
use App\Core\Exceptions\RedirectException;

class Auth extends BaseController
{
    public AuthService $service;
    private ?OidcConfig $oidcConfig;

    public function __construct(
        ?AuthService $service = null,
        ?OidcConfig $oidcConfig = null
    ) {
        parent::__construct();
        $this->service = $service ?? $this->service(AuthService::class);
        $this->oidcConfig = $oidcConfig;
    }

    #[Route(method: 'POST')]
    public function login(array $input): Response
    {
        return (new Response())->setError(410, 'La connexion par mot de passe est désactivée.');
    }
    #[Route(method: 'POST')]
    public function logout($input = [])
    {
        $this->service->logout();
        $config = $this->oidcConfig ?? $this->service(OidcConfig::class);
        throw new RedirectException($config->centralLogoutUrl . '?' . http_build_query(['return_to' => $config->postLogoutRedirectUri], '', '&', PHP_QUERY_RFC3986));
    }
}
