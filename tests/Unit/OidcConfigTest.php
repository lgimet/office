<?php
namespace Tests\Unit;
use App\Services\Oidc\OidcConfig;
use PHPUnit\Framework\TestCase;
final class OidcConfigTest extends TestCase
{
    protected function setUp():void{$_ENV=['OFFICE_OIDC_ISSUER'=>'https://api.devsys.fr','OFFICE_OIDC_CLIENT_ID'=>'office-web','OFFICE_OIDC_CLIENT_SECRET'=>'secret','OFFICE_OIDC_REDIRECT_URI'=>'https://office.test/callback','OFFICE_OIDC_RESOURCE'=>'https://api.test','OFFICE_OIDC_SCOPES'=>'openid profile','OFFICE_OIDC_HTTP_TIMEOUT'=>'10','OFFICE_OIDC_CONNECT_TIMEOUT'=>'5','OFFICE_CENTRAL_LOGOUT_URL'=>'https://api.devsys.fr/auth/logout','OFFICE_POST_LOGOUT_REDIRECT_URI'=>'https://office.devsys.fr/logged-out'];}
    public function testRequiredConfigurationIsLoaded():void{$config=OidcConfig::fromEnvironment();self::assertSame('office-web',$config->clientId);self::assertSame(['openid','profile'],$config->scopes);}
    /** @dataProvider invalidVariables */
    public function testInvalidConfigurationFailsFast(string $variable,string $value):void{$_ENV[$variable]=$value;$this->expectException(\RuntimeException::class);OidcConfig::fromEnvironment();}
    public static function invalidVariables():array{return [['OFFICE_OIDC_ISSUER',''],['OFFICE_OIDC_CLIENT_ID',''],['OFFICE_OIDC_CLIENT_SECRET',''],['OFFICE_OIDC_REDIRECT_URI',''],['OFFICE_OIDC_RESOURCE',''],['OFFICE_OIDC_SCOPES',''],['OFFICE_OIDC_HTTP_TIMEOUT','0'],['OFFICE_OIDC_CONNECT_TIMEOUT','-1'],['OFFICE_CENTRAL_LOGOUT_URL','http://api.devsys.fr/auth/logout'],['OFFICE_POST_LOGOUT_REDIRECT_URI','https://evil.example/logged-out']];}
    /** @dataProvider transitionPairs */
    public function testIssuerAndLogoutMustUseTheSameSupportedOrigin(string $issuer,string $logout,bool $valid):void{$_ENV['OFFICE_OIDC_ISSUER']=$issuer;$_ENV['OFFICE_CENTRAL_LOGOUT_URL']=$logout;if(!$valid)$this->expectException(\RuntimeException::class);$config=OidcConfig::fromEnvironment();if($valid)self::assertSame($issuer,$config->issuer);}
    public static function transitionPairs():array{return [['https://api.devsys.fr','https://api.devsys.fr/auth/logout',true],['https://login.devsys.fr','https://login.devsys.fr/auth/logout',true],['https://login.devsys.fr','https://api.devsys.fr/auth/logout',false],['http://login.devsys.fr','https://login.devsys.fr/auth/logout',false],['https://login.devsys.fr/path','https://login.devsys.fr/auth/logout',false],['https://login.devsys.fr?x=1','https://login.devsys.fr/auth/logout',false],['https://login.devsys.fr.evil.example','https://login.devsys.fr.evil.example/auth/logout',false]];}
}
