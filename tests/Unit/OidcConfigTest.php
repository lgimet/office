<?php
namespace Tests\Unit;
use App\Services\Oidc\OidcConfig;
use PHPUnit\Framework\TestCase;
final class OidcConfigTest extends TestCase
{
    protected function setUp():void{$_ENV=['OFFICE_OIDC_ISSUER'=>'https://issuer.test','OFFICE_OIDC_CLIENT_ID'=>'office-web','OFFICE_OIDC_CLIENT_SECRET'=>'secret','OFFICE_OIDC_REDIRECT_URI'=>'https://office.test/callback','OFFICE_OIDC_RESOURCE'=>'https://api.test','OFFICE_OIDC_SCOPES'=>'openid profile','OFFICE_OIDC_HTTP_TIMEOUT'=>'10','OFFICE_OIDC_CONNECT_TIMEOUT'=>'5'];}
    public function testRequiredConfigurationIsLoaded():void{$config=OidcConfig::fromEnvironment();self::assertSame('office-web',$config->clientId);self::assertSame(['openid','profile'],$config->scopes);}
    /** @dataProvider invalidVariables */
    public function testInvalidConfigurationFailsFast(string $variable,string $value):void{$_ENV[$variable]=$value;$this->expectException(\RuntimeException::class);OidcConfig::fromEnvironment();}
    public static function invalidVariables():array{return [['OFFICE_OIDC_ISSUER',''],['OFFICE_OIDC_CLIENT_ID',''],['OFFICE_OIDC_CLIENT_SECRET',''],['OFFICE_OIDC_REDIRECT_URI',''],['OFFICE_OIDC_RESOURCE',''],['OFFICE_OIDC_SCOPES',''],['OFFICE_OIDC_HTTP_TIMEOUT','0'],['OFFICE_OIDC_CONNECT_TIMEOUT','-1']];}
}
