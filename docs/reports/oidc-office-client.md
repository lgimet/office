# Rapport Point 7B — Office client OIDC

## Implémentation

Créés : `src/Services/Oidc/*`, `src/Providers/OfficeAccessTokenProvider.php`, `src/Controllers/Oidc.php`, `src/Views/Oidc/error.twig`, `tests/run.php` et la documentation OIDC/opérations. Modifiés : `AuthService`, contrôleurs auth, routeur, `DevsysApiProvider`, DI, bootstrap session, `.env.example`, Composer et documentation legacy. Supprimés : `JWTService.php` et `OAuthReturnUrlValidator.php`, inutilisés après migration.

Le flux utilise discovery, Authorization Code, PKCE S256, state, nonce, client secret Basic, ID Token obligatoire et `/userinfo`. L’ID Token est validé en RS256 via JWKS avec contrôle `alg`, `kid`, issuer, audience, expiration, iat, nonce, token_use, `sub=user:<UUID>` et `tenant_id=<UUID>`. Un `kid` inconnu provoque un rafraîchissement JWKS unique. Les identités ID Token/UserInfo sont croisées.

Le pending flow contient state, nonce, verifier, retour local et timestamp (TTL 600 s). La session contient `sub`, `user_uuid`, `tenant_uuid`, profil, initiales, scopes, access token serveur et expiration. Aucun ID Token ni refresh token n’est stocké. `session_regenerate_id(true)` est appelé après validation. Le cookie `office_session` est host-only, HttpOnly, `SameSite=Lax`, Secure en production. Le logout est local et l’expiration déclenche une ré-auth sans fallback.

`AuthService` est désormais une façade de session OIDC. `AuthRepository`, `UserRepository` et `users` restent conservés pour compatibilité mais n’authentifient plus. `DevsysApiProvider` reçoit l’Access Token utilisateur via `OfficeAccessTokenProvider`; `ClientsApi` ne dépend plus de `client_credentials`. Les variables client credentials sont retirées de `.env.example` car aucun usage restant n’a été trouvé.

## Vérifications

`composer validate` passe avec l’avertissement préexistant de licence manquante; `composer dump-autoload --no-interaction` passe. `composer test` passe avec 1 script et 10 assertions, dont un JWT RS256 réellement signé et validé via un JWKS de test. Le lint PHP passe sur `src` et `tests`. Les tests HTTP fake complets, le navigateur contre un Office-Api déployé et le cross-tenant restent à exécuter dans l’environnement d’intégration. Aucun dépôt externe n’a été modifié.

La migration ne couvre ni invoices, ni company settings, ni le stockage `dedicated`; 7B ne clôt donc pas le chantier tenant-aware global.
