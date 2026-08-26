<?php
declare(strict_types=1);
namespace App\Providers;
use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
final class DevsysApiProvider
{
    public static function create(OfficeAccessTokenProvider $tokens): DevsysApiClient { return new DevsysApiClient(new DevsysApiConfig(baseUrl:self::requiredEnvironment('DEVSYS_API_BASE_URL'),accessToken:$tokens->accessToken(),timeout:self::positiveFloat('DEVSYS_API_TIMEOUT',10.0),connectTimeout:self::positiveFloat('DEVSYS_API_CONNECT_TIMEOUT',5.0))); }
    private static function requiredEnvironment(string $name): string { $value=trim((string)($_ENV[$name]??'')); if($value==='') throw new \RuntimeException("La variable d’environnement $name est requise."); return $value; }
    private static function positiveFloat(string $name,float $default):float { $value=$_ENV[$name]??null; if($value===null||$value==='')return $default; if(!is_numeric($value)||(float)$value<=0)throw new \RuntimeException("La variable d’environnement $name doit être un nombre positif."); return (float)$value; }
}
