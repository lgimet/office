# Rapport Point 7B — Office client OIDC

## Implémentation

Créés : `src/Services/Oidc/*`, `src/Providers/OfficeAccessTokenProvider.php`, `src/Controllers/Oidc.php`, `src/Views/Oidc/error.twig`, `phpunit.xml`, `tests/Unit/*` et la documentation OIDC/opérations. Modifiés : `AuthService`, contrôleurs auth, routeur, `DevsysApiProvider`, DI, bootstrap session, `.env.example`, Composer et documentation legacy. Supprimés : `JWTService.php`, `OAuthReturnUrlValidator.php` et l’ancien runner `tests/run.php`.

Le flux utilise discovery, Authorization Code, PKCE S256, state, nonce, client secret Basic, ID Token obligatoire et `/userinfo`. L’ID Token est validé en RS256 via JWKS avec contrôle `alg`, `kid`, issuer, audience, expiration, iat, nonce, token_use, `sub=user:<UUID>` et `tenant_id=<UUID>`. Un `kid` inconnu provoque un rafraîchissement JWKS unique. Les identités ID Token/UserInfo sont croisées.

Le pending flow contient state, nonce, verifier, retour local et timestamp (TTL 600 s). `return_to` refuse les antislashs, contrôles ASCII, schemes, hosts, identifiants et fragments, tout en autorisant les query strings locales. La session contient `sub`, `user_uuid`, `tenant_uuid`, profil, initiales, scopes, access token serveur et expiration. Aucun ID Token ni refresh token n’est stocké. `session_regenerate_id(true)` est appelé après validation. Le cookie `office_session` est host-only, HttpOnly, `SameSite=Lax`, Secure en production et explicitement expiré au logout. `session.gc_maxlifetime` est aligné sur un `OFFICE_SESSION_TTL` positif. Le logout est local et l’expiration déclenche une ré-auth sans fallback.

`AuthService` est désormais une façade de session OIDC. `AuthRepository`, `UserRepository` et `users` restent conservés pour compatibilité mais n’authentifient plus. `DevsysApiProvider` reçoit l’Access Token utilisateur via `OfficeAccessTokenProvider`; `ClientsApi` ne dépend plus de `client_credentials`. Les variables client credentials sont retirées de `.env.example` car aucun usage restant n’a été trouvé.

La configuration `OidcConfig` valide fail-fast les variables OIDC requises et les timeouts positifs, sans jamais exposer le secret. `OfficeAccessTokenProvider` ne redirige que pour une session OIDC absente ou expirée; les erreurs techniques inattendues remontent normalement.

## Point 7C.5 — Logout en un seul clic

`POST /Auth/logout` conserve le CSRF Office, détruit la session locale et expire `office_session`, puis renvoie une page sans cache qui soumet automatiquement un formulaire POST vers `OFFICE_CENTRAL_RP_LOGOUT_URL`. Le formulaire contient uniquement le `return_to` fixe `https://office.devsys.fr/logged-out`; aucun token Office ou OIDC n’est exposé dans le HTML. L’allowlist correspondante doit être configurée séparément dans `Office-Api`. La route publique `GET /logged-out` est une page statique sans `AuthService::verify()`, avec cache désactivé et bouton de reconnexion vers `/`. Aucun `GET /Auth/logout` n’est ajouté.

Le navigateur navigue ainsi de `POST /Auth/logout` vers `POST https://login.devsys.fr/auth/logout/rp`, puis suit le `303` central vers `/logged-out`. Il s’agit d’une soumission de formulaire cross-origin, sans `fetch()` ni CORS; le cookie central reste donc géré naturellement par le navigateur. Les fichiers concernés sont `Auth.php`, `AuthService.php`, `Router.php`, `OidcConfig.php`, `.env.example`, les tests unitaires et cette documentation. Le Router conserve la validation CSRF centralisée et n’essaie pas de recréer un CSRF après destruction de la session.

Le premier test d’intégration réel a révélé que `Office-Api` exigeait aussi `client_id` dans le formulaire du grant Authorization Code. Le champ non secret `client_id=office-web` est désormais envoyé avec `Authorization: Basic base64(client_id:client_secret)`; aucun secret n’est ajouté au body.

## Point 7C.3 — Migration vers login.devsys.fr

Office est préparé pour utiliser `https://login.devsys.fr` comme issuer OIDC et comme cible de logout central. `OidcConfig` n’autorise temporairement que `https://api.devsys.fr` ou `https://login.devsys.fr`, exige un issuer HTTPS exact sans path/query/fragment/port et impose que `OFFICE_CENTRAL_LOGOUT_URL` et `OFFICE_CENTRAL_RP_LOGOUT_URL` partagent exactement son origin avec l’issuer actif, respectivement sur `/auth/logout` et `/auth/logout/rp`. Il n’existe donc pas de dual issuer runtime ni de mélange `login`/`api`.

`.env.example` cible désormais `login.devsys.fr`. `OFFICE_OIDC_RESOURCE` et `DEVSYS_API_BASE_URL` restent strictement sur `https://api.devsys.fr`; aucun appel métier n’est dirigé vers le login. L’ancien couple `api/api` reste accepté temporairement jusqu’au Point 7C.7. Le `.env` de production n’a pas été modifié et aucun secret n’est documenté.

## Vérifications

`composer validate` passe avec l’avertissement préexistant de licence manquante; `composer dump-autoload --no-interaction` passe. `composer test` passe avec PHPUnit : 63 tests et 95 assertions. La suite couvre return_to et antislashs/contrôles, authorization URL, state/nonce/PKCE, erreur fournisseur, échanges Basic avec `client_id`, réponses token invalides, refresh token non stocké, signature RS256/JWKS, claims, rotation de `kid`, session, logout global en POST automatique sans fuite de token, rejet CSRF du POST logout avant contrôleur, AuthService, provider de token, configuration fail-fast, validation stricte du RP logout et transition issuer/logout `api` ou `login`. Le lint PHP passe sur `src` et `tests`. Le test navigateur réel du logout central et le cross-tenant restent à exécuter dans l’environnement d’intégration. Aucun dépôt externe n’a été modifié.

La migration ne couvre ni invoices, ni company settings, ni le stockage `dedicated`; 7B ne clôt donc pas le chantier tenant-aware global.
