<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Services\OAuthReturnUrlValidator;

class Auth extends BaseController
{
    public AuthService $service;
    private OAuthReturnUrlValidator $oauthReturnUrlValidator;

    public function __construct(
        ?AuthService $service = null,
        ?OAuthReturnUrlValidator $oauthReturnUrlValidator = null
    ) {
        parent::__construct();
        $this->service = $service ?? $this->service(AuthService::class);
        $this->oauthReturnUrlValidator = $oauthReturnUrlValidator ?? new OAuthReturnUrlValidator();
    }

    #[Route(method: 'POST')]
    public function login(array $input): Response
    {
        $arg = $input['vars'] ?? [];
        $email = (string) ($arg['email'] ?? '');
        $password = (string) ($arg['password'] ?? '');

        $response = new Response();
        if (!$this->service->login($email, $password)) {
            return $response->setError(401, 'Adresse e-mail ou mot de passe incorrect.');
        }

        $returnTo = $this->oauthReturnUrlValidator->validate($arg['return_to'] ?? null);

        return $response->setRedirect($returnTo ?? '/dashboard');
    }
    #[Route(method: 'POST')]
    public function logout($input = [])
    {
        $this->service->logout();
    }
}
