<?php
namespace Tests\Unit;
use App\Services\Oidc\{CurlOidcHttpClient, LocalReturnToValidator, OidcClient, OidcDiscoveryProvider, OidcHttpClientInterface, OidcIdTokenValidator, OidcSessionService, OidcProtocolException, OidcValidationException};
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class OidcClientTest extends TestCase
{
    protected function setUp():void { parent::setUp(); if(session_status()===PHP_SESSION_ACTIVE) session_write_close(); session_save_path(sys_get_temp_dir()); session_name('oidc_test'); session_start(); $_SESSION=[]; }
    protected function tearDown():void { $_SESSION=[]; if(session_status()===PHP_SESSION_ACTIVE) session_write_close(); parent::tearDown(); }
    public function testAuthorizationUrlStoresStateNonceAndPkce():void
    {
        $http=new FakeOidcHttp(); $client=$this->client($http); parse_str((string)parse_url($client->authorizationUrl('/clients?id=1'),PHP_URL_QUERY),$query); self::assertSame('code',$query['response_type']); self::assertSame('office-web',$query['client_id']); self::assertSame('https://office.test/callback',$query['redirect_uri']); self::assertSame('openid profile email read:clients',$query['scope']); self::assertSame('https://api.test',$query['resource']); self::assertSame('S256',$query['code_challenge_method']); self::assertNotSame($query['state'],$query['nonce']); self::assertSame('/clients?id=1',$_SESSION['oidc_pending']['return_to']); self::assertSame($query['code_challenge'],$this->b64(hash('sha256',$_SESSION['oidc_pending']['code_verifier'],true)));
    }
    public function testCallbackRejectsMissingExpiredAndWrongStateAndClearsPending():void
    {
        foreach([[],['state'=>'wrong'],['state'=>'ok','created_at'=>time()-601]] as $pending){$_SESSION['oidc_pending']=$pending; try{$this->client(new FakeOidcHttp())->callback(['state'=>'ok','code'=>'c']);self::fail();}catch(OidcValidationException|OidcProtocolException){} self::assertNull($_SESSION['oidc_pending']);}
    }
    public function testProviderErrorDoesNotExchangeCode():void
    { $_SESSION['oidc_pending']=['state'=>'ok','nonce'=>'n','code_verifier'=>'v','return_to'=>'/dashboard','created_at'=>time()]; $http=new FakeOidcHttp(); try{$this->client($http)->callback(['state'=>'ok','error'=>'access_denied']);self::fail();}catch(OidcProtocolException){} self::assertFalse($http->tokenCalled); self::assertArrayNotHasKey('office_identity',$_SESSION); }
    /** @dataProvider invalidTokenResponses */
    public function testInvalidTokenResponsesAreRejected(array $response):void
    { $_SESSION['oidc_pending']=['state'=>'ok','nonce'=>'n','code_verifier'=>'v','return_to'=>'/dashboard','created_at'=>time()]; $http=new FakeOidcHttp($response); try{$this->client($http)->callback(['state'=>'ok','code'=>'c']);self::fail();}catch(OidcProtocolException){} self::assertNull($_SESSION['oidc_pending']); }
    public static function invalidTokenResponses():array { return [[['status'=>400,'body'=>[]]],[['status'=>200,'body'=>['id_token'=>'x','token_type'=>'Bearer','expires_in'=>60]]],[['status'=>200,'body'=>['access_token'=>1,'id_token'=>'x','token_type'=>'Bearer','expires_in'=>60]]],[['status'=>200,'body'=>['access_token'=>'a','id_token'=>'x','token_type'=>'Basic','expires_in'=>60]]],[['status'=>200,'body'=>['access_token'=>'a','id_token'=>'x','token_type'=>'Bearer','expires_in'=>0]]],[['status'=>200,'body'=>['access_token'=>'a','token_type'=>'Bearer','expires_in'=>60]]]]; }
    public function testSuccessfulCallbackUsesBasicAndStoresRefreshToken():void
    { $_SESSION['oidc_pending']=['state'=>'ok','nonce'=>'n','code_verifier'=>'v','return_to'=>'/dashboard','created_at'=>time()]; $http=new FakeOidcHttp(['status'=>200,'body'=>['access_token'=>'access','id_token'=>'','token_type'=>'Bearer','expires_in'=>3600,'scope'=>'openid read:clients','refresh_token'=>'refresh']]); $http->tokenResponse['body']['id_token']=$this->jwt($http); self::assertSame('/dashboard',$this->client($http)->callback(['state'=>'ok','code'=>'code'])); self::assertSame(['grant_type'=>'authorization_code','client_id'=>'office-web','code'=>'code','redirect_uri'=>'https://office.test/callback','code_verifier'=>'v'],$http->fields); self::assertSame(['office-web','secret'],$http->basicAuth); self::assertSame('refresh',$_SESSION['office_oauth']['refresh_token']); self::assertSame('access',$_SESSION['office_oauth']['access_token']); }
    private function client(FakeOidcHttp $http):OidcClient { $discovery=new OidcDiscoveryProvider($http,'https://issuer.test',0); return new OidcClient($http,$discovery,new OidcIdTokenValidator($http,$discovery,'https://issuer.test','office-web'),new OidcSessionService(),new LocalReturnToValidator(),'office-web','secret','https://office.test/callback','https://issuer.test','https://api.test',['openid','profile','email','read:clients']); }
    private function jwt(FakeOidcHttp $http):string { $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]); $details=openssl_pkey_get_details($key); $http->jwks=['keys'=>[['kty'=>'RSA','alg'=>'RS256','kid'=>'test','n'=>$this->b64($details['rsa']['n']),'e'=>$this->b64($details['rsa']['e'])]]]; return JWT::encode(['iss'=>'https://issuer.test','aud'=>'office-web','sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001','nonce'=>'n','token_use'=>'id_token','iat'=>time(),'exp'=>time()+300],$key,'RS256','test'); }
    private $key;
    private function b64(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    private function b64decode(string $value):string{return base64_decode(strtr($value,'-_','+/').str_repeat('=',(4-strlen($value)%4)%4));}
    /** @dataProvider userInfoFailures */
    public function testUserInfoFailuresAreRejected(int $status,array $body):void
    { $_SESSION['oidc_pending']=['state'=>'ok','nonce'=>'n','code_verifier'=>'v','return_to'=>'/dashboard','created_at'=>time()]; $http=new FakeOidcHttp(); $http->tokenResponse=['status'=>200,'body'=>['access_token'=>'access','id_token'=>$this->jwt($http),'token_type'=>'Bearer','expires_in'=>3600]]; $http->userinfoResponse=['status'=>$status,'body'=>$body,'headers'=>[]]; try{$this->client($http)->callback(['state'=>'ok','code'=>'code']);self::fail();}catch(OidcProtocolException|\App\Services\Oidc\OidcValidationException){} self::assertArrayNotHasKey('office_identity',$_SESSION); }
    public static function userInfoFailures():array{return [[401,[]],[500,[]],[200,['sub'=>'different','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001']],[200,['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'different']]];}
    }
final class FakeOidcHttp implements OidcHttpClientInterface
{
    public bool $tokenCalled=false; public array $fields=[]; public ?array $basicAuth=null; public array $jwks=[]; public array $tokenResponse; public array $userinfoResponse=['status'=>200,'body'=>['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001','name'=>'Ada Lovelace'],'headers'=>[]];
    public function __construct(array $tokenResponse=['status'=>200,'body'=>[]]){$this->tokenResponse=$tokenResponse;}
    public function getJson(string $url,array $headers=[]):array { if(str_ends_with($url,'/jwks')) return ['status'=>200,'body'=>$this->jwks,'headers'=>[]]; return ['status'=>200,'body'=>['issuer'=>'https://issuer.test','authorization_endpoint'=>'https://issuer.test/authorize','token_endpoint'=>'https://issuer.test/token','userinfo_endpoint'=>'https://issuer.test/userinfo','jwks_uri'=>'https://issuer.test/jwks'],'headers'=>[]]; }
    public function postForm(string $url,array $fields,?array $basicAuth=null):array{$this->tokenCalled=true;$this->fields=$fields;$this->basicAuth=$basicAuth;return $this->tokenResponse;}
    public function getBearer(string $url,string $token):array{return $this->userinfoResponse;}
}
