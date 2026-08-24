<?php
namespace App\Controllers;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Core\Exceptions\RedirectException;
use App\Services\Oidc\OidcClient;
final class Oidc extends BaseController
{
    public function __construct(private readonly OidcClient $oidc) { parent::__construct(); }
    #[Route(method:'GET',path:'auth/oidc/login')]
    public function login(): void { throw new RedirectException($this->oidc->authorizationUrl($_GET['return_to'] ?? null)); }
    #[Route(method:'GET',path:'auth/oidc/callback')]
    public function callback(): void { try { throw new RedirectException($this->oidc->callback($_GET)); } catch(RedirectException $e){throw $e;} catch(\Throwable){ echo $this->render('error.twig',['page'=>'login','message'=>'La connexion à DevSys a échoué.']); } }
}
