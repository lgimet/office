<?php
namespace App\Controllers;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Core\Exceptions\RedirectException;
use App\Services\Oidc\OidcClient;
use App\Services\Oidc\OidcErrorContext;
final class Oidc extends BaseController
{
    public function __construct(private readonly OidcClient $oidc) { parent::__construct(); }
    #[Route(method:'GET',path:'auth/oidc/login')]
    public function login(): void { throw new RedirectException($this->oidc->authorizationUrl($_GET['return_to'] ?? null)); }
    #[Route(method:'GET',path:'auth/oidc/callback')]
    public function callback(): void { try { throw new RedirectException($this->oidc->callback($_GET)); } catch(RedirectException $e){throw $e;} catch(\Throwable $exception){ $code=OidcErrorContext::classify($exception); OidcErrorContext::log($exception,$code); OidcErrorContext::store($code); throw new RedirectException('/auth/error'); } }
    #[Route(method:'GET',path:'auth/error')]
    public function error(): void { header('Cache-Control: no-store'); header('Pragma: no-cache'); $code=OidcErrorContext::consume(); echo $this->render('error.twig',['page'=>'login','message'=>OidcErrorContext::message($code)]); }
}
