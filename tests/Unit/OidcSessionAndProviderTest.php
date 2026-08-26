<?php
namespace Tests\Unit;
use App\Core\Exceptions\RedirectException;
use App\Providers\OfficeAccessTokenProvider;
use App\Services\AuthService;
use App\Services\Oidc\{OidcIdentityException, OidcSessionExpiredException, OidcSessionService};
use PHPUnit\Framework\TestCase;
final class OidcSessionAndProviderTest extends TestCase
{
    protected function setUp():void{parent::setUp();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_save_path(sys_get_temp_dir());session_name('oidc_session_test');session_start();$_SESSION=[];}
    protected function tearDown():void{$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_write_close();parent::tearDown();}
    public function testSessionIdentityTokenExpiryAuthFacadeAndLogout():void{$s=new OidcSessionService();$c=(object)['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001'];$s->create($c,['sub'=>$c->sub,'tenant_id'=>$c->tenant_id,'given_name'=>'Ada','family_name'=>'Lovelace','email'=>'ada@example.test'],3600,['openid','read:clients']);$s->storeAccessToken('token',3600);$auth=new AuthService($s);$user=$auth->verify();self::assertSame($c->sub,$user->sub);self::assertSame($c->tenant_id,$user->tenant_uuid);self::assertSame('Ada',$user->firstname);self::assertArrayHasKey('initials',$_SESSION['office_identity']);self::assertSame('token',(new OfficeAccessTokenProvider($s))->accessToken());$_SESSION['office_oauth']['expires_at']=time();self::assertFalse($s->isAuthenticated());try{$auth->verify(false);self::fail();}catch(RedirectException $e){self::assertStringStartsWith('/auth/oidc/login',$e->getLocation());}$s->logout();self::assertSame([],$_SESSION);}
    public function testProviderDoesNotConvertUnexpectedErrorsToRedirect():void{$mock=$this->createMock(OidcSessionService::class);$mock->method('accessToken')->willThrowException(new \RuntimeException('bug'));$this->expectException(\RuntimeException::class);(new OfficeAccessTokenProvider($mock))->accessToken();}
    /** @dataProvider incoherentUserInfo */
    public function testIncoherentUserInfoIsClassifiedAsIdentityError(array $userinfo):void{$claims=(object)['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001'];$this->expectException(OidcIdentityException::class);(new OidcSessionService())->create($claims,$userinfo,3600,['openid']);}
    public static function incoherentUserInfo():array{return [[['sub'=>'user:123e4567-e89b-12d3-a456-426614174999','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001']],[['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174999']],[['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'not-a-uuid']]];}
}
