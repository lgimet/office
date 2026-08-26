<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Services\Oidc\OidcConfig;

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
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        $action = htmlspecialchars($config->centralRpLogoutUrl, ENT_QUOTES, 'UTF-8');
        $returnTo = htmlspecialchars($config->postLogoutRedirectUri, ENT_QUOTES, 'UTF-8');
        return (new Response())->setPage('<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Déconnexion</title></head><body><p>La déconnexion est en cours…</p><form id="central-logout" method="post" action="' . $action . '"><input type="hidden" name="return_to" value="' . $returnTo . '"><noscript><p>La déconnexion doit être terminée.</p><button type="submit">Terminer la déconnexion</button></noscript></form><script>document.getElementById("central-logout").submit();</script></body></html>');
    }
}
