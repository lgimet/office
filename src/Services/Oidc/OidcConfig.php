<?php
namespace App\Services\Oidc;

final class OidcConfig
{
    public function __construct(public readonly string $issuer, public readonly string $clientId, public readonly string $clientSecret, public readonly string $redirectUri, public readonly string $resource, public readonly array $scopes, public readonly float $httpTimeout, public readonly float $connectTimeout, public readonly string $centralLogoutUrl, public readonly string $centralRpLogoutUrl, public readonly string $postLogoutRedirectUri) {}
    public static function fromEnvironment(): self
    {
        $required = static function (string $name): string { $value=trim((string)($_ENV[$name]??'')); if($value==='') throw new \RuntimeException("La configuration OIDC $name est requise."); return $value; };
        $positive = static function (string $name, float $default): float { $value=$_ENV[$name]??$default; if(!is_numeric($value)||(float)$value<=0) throw new \RuntimeException("La configuration OIDC $name doit être positive."); return (float)$value; };
        $scopes = preg_split('/\s+/', $required('OFFICE_OIDC_SCOPES')) ?: [];
        $logoutUrl = $required('OFFICE_CENTRAL_LOGOUT_URL');
        $rpLogoutUrl = $required('OFFICE_CENTRAL_RP_LOGOUT_URL');
        $postLogout = $required('OFFICE_POST_LOGOUT_REDIRECT_URI');
        $issuer = $required('OFFICE_OIDC_ISSUER');
        self::validateIssuer($issuer);
        self::validateLogoutUrl($logoutUrl, $issuer);
        self::validateLogoutUrl($rpLogoutUrl, $issuer, '/auth/logout/rp');
        self::validateExactHttpsUrl($postLogout, 'office.devsys.fr', '/logged-out', 'OFFICE_POST_LOGOUT_REDIRECT_URI');
        return new self($issuer,$required('OFFICE_OIDC_CLIENT_ID'),$required('OFFICE_OIDC_CLIENT_SECRET'),$required('OFFICE_OIDC_REDIRECT_URI'),$required('OFFICE_OIDC_RESOURCE'),$scopes,$positive('OFFICE_OIDC_HTTP_TIMEOUT',10),$positive('OFFICE_OIDC_CONNECT_TIMEOUT',5),$logoutUrl,$rpLogoutUrl,$postLogout);
    }
    private static function validateIssuer(string $issuer):void { $parts=parse_url($issuer); if(!is_array($parts)||($parts['scheme']??null)!=='https'||($parts['host']??null)!=='login.devsys.fr'||($parts['path']??'')!==''||isset($parts['port'])||isset($parts['query'])||isset($parts['fragment'])||isset($parts['user'])||isset($parts['pass'])) throw new \RuntimeException('La configuration OIDC OFFICE_OIDC_ISSUER est invalide.'); }
    private static function validateLogoutUrl(string $url,string $issuer,string $path='/auth/logout'):void { $issuerParts=parse_url($issuer); $logoutParts=parse_url($url); if(!is_array($issuerParts)||!is_array($logoutParts)||($logoutParts['scheme']??null)!=='https'||($logoutParts['scheme']??null)!==($issuerParts['scheme']??null)||($logoutParts['host']??null)!==($issuerParts['host']??null)||($logoutParts['path']??null)!==$path||isset($logoutParts['port'])||isset($logoutParts['query'])||isset($logoutParts['fragment'])||isset($logoutParts['user'])||isset($logoutParts['pass'])) throw new \RuntimeException('La configuration OIDC logout doit partager exactement l’origine de l’issuer et utiliser le chemin attendu.'); }
    private static function validateExactHttpsUrl(string $url,string $host,string $path,string $name):void { $parts=parse_url($url); if(!is_array($parts)||($parts['scheme']??null)!=='https'||($parts['host']??null)!==$host||($parts['path']??null)!==$path||isset($parts['port'])||isset($parts['query'])||isset($parts['fragment'])||isset($parts['user'])||isset($parts['pass'])) throw new \RuntimeException("La configuration OIDC $name est invalide."); }
}
