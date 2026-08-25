<?php
namespace Tests\Unit;
use App\Services\Oidc\{OidcDiscoveryProvider, OidcHttpClientInterface, OidcIdTokenValidator, OidcValidationException};
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
final class OidcIdTokenValidatorTest extends TestCase
{
    private $privateKey; private array $jwks=[]; private array $otherJwks=[];
    protected function setUp():void{$this->privateKey=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$d=openssl_pkey_get_details($this->privateKey);$key=['kty'=>'RSA','alg'=>'RS256','kid'=>'one','n'=>$this->b64($d['rsa']['n']),'e'=>$this->b64($d['rsa']['e'])];$this->jwks=['keys'=>[$key]];$key['kid']='other';$this->otherJwks=['keys'=>[$key]];}
    /** @dataProvider invalidClaims */
    public function testInvalidClaimsAreRejected(array $change):void{$this->expectException(OidcValidationException::class);$this->validator(new ValidatorHttp([$this->jwks]))->validate($this->token($change),'nonce');}
    public static function invalidClaims():array{return [[['iss'=>'wrong']],[['aud'=>'wrong']],[['nonce'=>'wrong']],[['token_use'=>'access']],[['sub'=>'not-user']],[['tenant_id'=>'not-uuid']]];}
    public function testInvalidSignatureIsRejected():void{$parts=explode('.',$this->token());$signature=base64_decode(strtr($parts[2],'-_','+/'));$signature[0]=chr(ord($signature[0])^1);$parts[2]=$this->b64($signature);$this->expectException(OidcValidationException::class);$this->validator(new ValidatorHttp([$this->jwks]))->validate(implode('.',$parts),'nonce');}
    public function testAlgorithmAndKidHeadersAreRejected():void
    { $badAlg=JWT::encode(['iss'=>'https://issuer.test','aud'=>'office-web','sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001','nonce'=>'nonce','token_use'=>'id_token','iat'=>time(),'exp'=>time()+300],'01234567890123456789012345678901','HS256','one');$this->expectException(OidcValidationException::class);$this->validator(new ValidatorHttp([$this->jwks]))->validate($badAlg,'nonce'); }
    public function testUnknownKidRefreshesJwksOnceAndCanSucceed():void{$http=new ValidatorHttp([$this->otherJwks,$this->jwks]);$token=$this->token();self::assertSame('user:123e4567-e89b-12d3-a456-426614174000',$this->validator($http)->validate($token,'nonce')->sub);self::assertSame(2,$http->jwksCalls);}
    public function testUnknownKidAfterRefreshIsRejected():void{$http=new ValidatorHttp([$this->otherJwks,$this->otherJwks]);$this->expectException(OidcValidationException::class);$this->validator($http)->validate($this->token(),'nonce');self::assertSame(2,$http->jwksCalls);}
    private function validator(ValidatorHttp $http):OidcIdTokenValidator{$d=new OidcDiscoveryProvider($http,'https://issuer.test',0);return new OidcIdTokenValidator($http,$d,'https://issuer.test','office-web');}
    private function token(array $change=[]):string{$claims=array_merge(['iss'=>'https://issuer.test','aud'=>'office-web','sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001','nonce'=>'nonce','token_use'=>'id_token','iat'=>time(),'exp'=>time()+300],$change);return JWT::encode($claims,$this->privateKey,'RS256','one');}
    private function b64(string $v):string{return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
}
final class ValidatorHttp implements OidcHttpClientInterface
{
    public int $jwksCalls=0; public function __construct(private array $jwks){}
    public function getJson(string $url,array $headers=[]):array{if(str_ends_with($url,'/jwks')){$item=$this->jwks[min($this->jwksCalls,count($this->jwks)-1)];$this->jwksCalls++;return ['status'=>200,'body'=>$item,'headers'=>[]];}return ['status'=>200,'body'=>['issuer'=>'https://issuer.test','authorization_endpoint'=>'https://issuer.test/authorize','token_endpoint'=>'https://issuer.test/token','userinfo_endpoint'=>'https://issuer.test/userinfo','jwks_uri'=>'https://issuer.test/jwks'],'headers'=>[]];}
    public function postForm(string $url,array $fields,?array $basicAuth=null):array{throw new \LogicException('unused');}
    public function getBearer(string $url,string $token):array{throw new \LogicException('unused');}
}
