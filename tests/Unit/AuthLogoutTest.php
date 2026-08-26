<?php
namespace Tests\Unit;
use App\Controllers\Auth;
use App\Services\AuthService;
use App\Services\Oidc\{OidcConfig, OidcSessionService};
use PHPUnit\Framework\TestCase;
final class AuthLogoutTest extends TestCase
{
    protected function setUp():void{parent::setUp();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_save_path(sys_get_temp_dir());session_name('logout_test');session_start();$_SESSION=['office_identity'=>['sub'=>'user:test'],'office_oauth'=>['access_token'=>'secret-token']];}
    protected function tearDown():void{$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_write_close();parent::tearDown();}
    public function testLogoutReturnsAutoSubmittingCentralPostFormWithoutTokens():void{$config=$this->config();$result=(new Auth(new AuthService(new OidcSessionService()),$config))->logout();self::assertStringContainsString('method="post"',$this->page($result));self::assertStringContainsString('action="https://login.devsys.fr/auth/logout/rp"',$this->page($result));self::assertStringContainsString('name="return_to"',$this->page($result));self::assertStringContainsString('https://office.devsys.fr/logged-out',$this->page($result));self::assertStringContainsString('getElementById("central-logout").submit()',$this->page($result));self::assertStringNotContainsString('secret-token',$this->page($result));self::assertArrayNotHasKey('office_identity',$_SESSION);self::assertArrayNotHasKey('office_oauth',$_SESSION);}
    private function config():OidcConfig{return new OidcConfig('https://login.devsys.fr','office-web','secret','https://office.test/callback','https://api.test',['openid'],10,5,'https://login.devsys.fr/auth/logout','https://login.devsys.fr/auth/logout/rp','https://office.devsys.fr/logged-out');}
    private function page(object $response):string{$ref=new \ReflectionClass($response);$prop=$ref->getProperty('page');$prop->setAccessible(true);return (string)$prop->getValue($response);}
}
