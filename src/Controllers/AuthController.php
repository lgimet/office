<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Services\AuthService;
use App\Services\OAuthReturnUrlValidator;

class AuthController extends BaseController
{
    private OAuthReturnUrlValidator $oauthReturnUrlValidator;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->oauthReturnUrlValidator = new OAuthReturnUrlValidator();
        $this->authService = $this->service(AuthService::class);
    }

    public function index()
    {
        $this->renderMainLogin();
    }

    #[Route(method: 'GET', path: 'login')]
    public function login(): void
    {
        $this->renderMainLogin();
    }

    public function renderAlreadyLoggedIn()
    {

        echo $this->render(
            'already_logged_in.twig',
            [
            'user' => $_SESSION['user']
            ]
        );
    }

    private function renderMainLogin()
    {
        $returnTo = $this->oauthReturnUrlValidator->validate($_GET['return_to'] ?? null);

        if ($returnTo !== null && $this->authService->isAuthenticated()) {
            header('Cache-Control: no-store');
            header('Pragma: no-cache');
            header('Location: ' . $returnTo, true, 302);
            exit;
        }

        echo $this->render(
            'login.twig',
            [
                'page' => 'login',
                'return_to' => $returnTo,
            ]
        );
    }
}
