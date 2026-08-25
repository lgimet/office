<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Services\AuthService;
use App\Services\Oidc\LocalReturnToValidator;

class AuthController extends BaseController
{
    private LocalReturnToValidator $oauthReturnUrlValidator;
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->oauthReturnUrlValidator = new LocalReturnToValidator();
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

    #[Route(method: 'GET', path: 'logged-out')]
    public function loggedOut(): void
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        echo $this->render('logged-out.twig', ['page' => 'login']);
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

        if ($this->authService->isAuthenticated()) {
            header('Cache-Control: no-store');
            header('Pragma: no-cache');
            header('Location: ' . ($returnTo ?: '/dashboard'), true, 302);
            exit;
        }

        header('Location: /auth/oidc/login' . ($returnTo ? '?return_to=' . rawurlencode($returnTo) : ''));
    }
}
