<?php
require __DIR__ . '/../vendor/autoload.php';
session_save_path('/tmp');
session_start();
use App\Services\Oidc\LocalReturnToValidator;
use App\Services\Oidc\OidcSessionService;
use App\Services\Oidc\OidcHttpClientInterface;
use App\Services\Oidc\OidcDiscoveryProvider;
use App\Services\Oidc\OidcIdTokenValidator;
use Firebase\JWT\JWT;
$passed=0; $assert=static function(bool $ok,string $name)use(&$passed):void{if(!$ok)throw new RuntimeException("FAIL: $name");$passed++;};
$v=new LocalReturnToValidator();
$assert($v->validate('/clients?id=1')==='/clients?id=1','local return_to');
$assert($v->validate('https://evil.example')==='/dashboard','absolute return_to rejected');
$assert($v->validate('//evil.example')==='/dashboard','network-path return_to rejected');
$assert($v->validate('javascript:alert(1)')==='/dashboard','javascript return_to rejected');
$assert($v->validate('/clients#external')==='/dashboard','fragment return_to rejected');
$_SESSION=[]; $s=new OidcSessionService(); $c=(object)['sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001'];
$s->create($c,['sub'=>$c->sub,'tenant_id'=>$c->tenant_id,'name'=>'Ada Lovelace'],3600,['openid','read:clients']); $s->storeAccessToken('user-token',3600);
$assert($s->isAuthenticated(),'OIDC session authenticated'); $assert($s->identity()['user_uuid']==='123e4567-e89b-12d3-a456-426614174000','canonical user UUID'); $assert(!array_key_exists('refresh_token',$_SESSION),'refresh token absent');
$_SESSION['office_oauth']['expires_at']=time()-1; $assert(!$s->isAuthenticated(),'expired access token rejected');

$key = openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
$details = openssl_pkey_get_details($key); $b64=static fn(string $x):string=>rtrim(strtr(base64_encode($x),'+/','-_'),'=');
$fake = new class($details, $b64) implements OidcHttpClientInterface {
    public function __construct(private array $details, private $b64) {}
    public function getJson(string $url,array $headers=[]):array { if(str_ends_with($url,'configuration')) return ['status'=>200,'body'=>['issuer'=>'https://issuer.test','authorization_endpoint'=>'https://issuer.test/authorize','token_endpoint'=>'https://issuer.test/token','userinfo_endpoint'=>'https://issuer.test/userinfo','jwks_uri'=>'https://issuer.test/jwks'],'headers'=>[]]; return ['status'=>200,'body'=>['keys'=>[['kty'=>'RSA','alg'=>'RS256','kid'=>'test','n'=>($this->b64)($this->details['rsa']['n']),'e'=>($this->b64)($this->details['rsa']['e'])]]],'headers'=>[]]; }
    public function postForm(string $url,array $fields,?array $basicAuth=null):array{throw new RuntimeException('unused');}
    public function getBearer(string $url,string $token):array{throw new RuntimeException('unused');}
};
$discovery=new OidcDiscoveryProvider($fake,'https://issuer.test'); $validator=new OidcIdTokenValidator($fake,$discovery,'https://issuer.test','office-web');
$jwt=JWT::encode(['iss'=>'https://issuer.test','aud'=>'office-web','sub'=>'user:123e4567-e89b-12d3-a456-426614174000','tenant_id'=>'123e4567-e89b-12d3-a456-426614174001','nonce'=>'nonce','token_use'=>'id_token','iat'=>time(),'exp'=>time()+300],$key,'RS256','test');
$assert($validator->validate($jwt,'nonce')->sub==='user:123e4567-e89b-12d3-a456-426614174000','real RS256/JWKS validation');
echo "OK: {$passed} assertions\n";
