# Rapport Point 7B — Office client OIDC

## Implémentation

Créés : `src/Services/Oidc/*`, `src/Providers/OfficeAccessTokenProvider.php`, `src/Controllers/Oidc.php`, `src/Views/Oidc/error.twig`, `phpunit.xml`, `tests/Unit/*` et la documentation OIDC/opérations. Modifiés : `AuthService`, contrôleurs auth, routeur, `DevsysApiProvider`, DI, bootstrap session, `.env.example`, Composer et documentation legacy. Supprimés : `JWTService.php`, `OAuthReturnUrlValidator.php` et l’ancien runner `tests/run.php`.

Le flux utilise discovery, Authorization Code, PKCE S256, state, nonce, client secret Basic, ID Token obligatoire et `/userinfo`. L’ID Token est validé en RS256 via JWKS avec contrôle `alg`, `kid`, issuer, audience, expiration, iat, nonce, token_use, `sub=user:<UUID>` et `tenant_id=<UUID>`. Un `kid` inconnu provoque un rafraîchissement JWKS unique. Les identités ID Token/UserInfo sont croisées.

Le pending flow contient state, nonce, verifier, retour local et timestamp (TTL 600 s). `return_to` refuse les antislashs, contrôles ASCII, schemes, hosts, identifiants et fragments, tout en autorisant les query strings locales. La session contient `sub`, `user_uuid`, `tenant_uuid`, profil, initiales, scopes, access token serveur et expiration. Aucun ID Token ni refresh token n’est stocké. `session_regenerate_id(true)` est appelé après validation. Le cookie `office_session` est host-only, HttpOnly, `SameSite=Lax`, Secure en production et explicitement expiré au logout. `session.gc_maxlifetime` est aligné sur un `OFFICE_SESSION_TTL` positif. Le logout est local et l’expiration déclenche une ré-auth sans fallback.

`AuthService` est désormais une façade de session OIDC. `AuthRepository`, `UserRepository` et `users` restent conservés pour compatibilité mais n’authentifient plus. `DevsysApiProvider` reçoit l’Access Token utilisateur via `OfficeAccessTokenProvider`; `ClientsApi` ne dépend plus de `client_credentials`. Les variables client credentials sont retirées de `.env.example` car aucun usage restant n’a été trouvé.

La configuration `OidcConfig` valide fail-fast les variables OIDC requises et les timeouts positifs, sans jamais exposer le secret. `OfficeAccessTokenProvider` ne redirige que pour une session OIDC absente ou expirée; les erreurs techniques inattendues remontent normalement.

## Vérifications

`composer validate` passe avec l’avertissement préexistant de licence manquante; `composer dump-autoload --no-interaction` passe. `composer test` passe avec PHPUnit : 42 tests et 65 assertions. La suite couvre return_to et antislashs/contrôles, authorization URL, state/nonce/PKCE, erreur fournisseur, échanges Basic, réponses token invalides, refresh token non stocké, signature RS256/JWKS, claims, rotation de `kid`, session, logout, AuthService, provider de token et configuration fail-fast. Le lint PHP passe sur `src` et `tests`. Le navigateur contre un Office-Api déployé et le cross-tenant restent à exécuter dans l’environnement d’intégration. Aucun dépôt externe n’a été modifié.

La migration ne couvre ni invoices, ni company settings, ni le stockage `dedicated`; 7B ne clôt donc pas le chantier tenant-aware global.
