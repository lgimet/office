<?php
namespace Tests\Unit;
use App\Controllers\Auth;
use App\Core\Exceptions\RedirectException;
use App\Services\AuthService;
use App\Services\Oidc\{OidcConfig, OidcSessionService};
use PHPUnit\Framework\TestCase;
final class AuthLogoutTest extends TestCase
{
    protected function setUp():void{parent::setUp();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_save_path(sys_get_temp_dir());session_name('logout_test');session_start();$_SESSION=['office_identity'=>['sub'=>'user:test'],'office_oauth'=>['access_token'=>'x']];}
    protected function tearDown():void{$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_write_close();parent::tearDown();}
    public function testPostLogoutRedirectsToCentralLogoutWithFixedReturnTo():void{$config=new OidcConfig('https://issuer.test','office-web','secret','https://office.test/callback','https://api.test',['openid'],10,5,'https://api.devsys.fr/auth/logout','https://office.devsys.fr/logged-out');$controller=new Auth(new AuthService(new OidcSessionService()),$config);$this->expectException(RedirectException::class);try{$controller->logout();}catch(RedirectException $e){self::assertSame('https://api.devsys.fr/auth/logout?return_to=https%3A%2F%2Foffice.devsys.fr%2Flogged-out',$e->getLocation());throw $e;}}
}
