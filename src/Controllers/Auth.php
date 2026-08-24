<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Services\Oidc\OidcClient;

class Auth extends BaseController
{
    public AuthService $service;
    private OidcClient $oidc;

    public function __construct(
        ?AuthService $service = null,
        ?OidcClient $oidc = null
    ) {
        parent::__construct();
        $this->service = $service ?? $this->service(AuthService::class);
        $this->oidc = $oidc ?? $this->service(OidcClient::class);
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
    }
}
