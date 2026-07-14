<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Attributes\Route;
use App\Helpers\Response;
use App\Services\AuthService;

class Auth extends BaseController {
    
    public AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        parent::__construct();
        $this->service = $service ?? $this->service(AuthService::class);
    }
    #[Route(method: 'POST')]
    public function login($input) {
        $arg = $input['vars'];
        
        $response = new Response();
        if( !$this->service->login($arg['email'],$arg['password']) ) {
            return $response->setError(401, 'Adresse e-mail ou mot de passe incorrect.');
        }
        return $response->setRedirect('/dashboard');
    }
    #[Route(method: 'POST')]
    public function logout($input = []) {
        $this->service->logout();
    }
}
