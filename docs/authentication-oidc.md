# Authentification OIDC d’Office

Office est un client OIDC confidentiel (`office-web`) et `Office-Api` est l’OpenID Provider. Le navigateur démarre `GET /auth/oidc/login`; Office récupère la discovery, génère `state`, `nonce` et `code_verifier` PKCE S256, puis redirige vers l’authorization endpoint.

```text
Browser -> Office /auth/oidc/login -> Office-Api /oauth/authorize
       -> Office /auth/oidc/callback -> /oauth/token (Basic)
       -> validation ID Token + JWKS -> /userinfo
       -> session PHP -> Access Token utilisateur -> /api/v1/clients
```

Le flow pending est conservé dans `$_SESSION['oidc_pending']` 600 secondes. `return_to` est un chemin local strict : antislashs, contrôles ASCII, schemes, hosts, identifiants et fragments sont refusés; les query strings locales restent autorisées. L’ID Token est validé pour issuer, audience `office-web`, signature RS256, `kid`, `exp`, `iat`, `nonce`, `token_use`, `sub=user:<UUID>` et `tenant_id=<UUID>`; `sub` et `tenant_id` doivent correspondre à `/userinfo`.

La session contient `office_identity` (identité canonique, tenant, profil, initiales et scopes) et `office_oauth` (Access Token et expiration). L’ID Token et le refresh token ne sont pas stockés. Le cookie est host-only, HttpOnly, `SameSite=Lax` et Secure en production; le logout expire explicitement ce cookie avec les mêmes paramètres. `session.gc_maxlifetime` est aligné sur `OFFICE_SESSION_TTL`, qui doit être strictement positif. À moins de 30 secondes de l’expiration, une nouvelle authentification OIDC est requise.

`AuthService` reste la façade des contrôleurs, mais ne vérifie plus de mot de passe et n’émet plus de JWT. `ClientsApi` utilise exclusivement l’Access Token utilisateur; aucun fallback `client_credentials` n’existe.

Le logout est global : le POST Office avec CSRF détruit la session locale puis redirige le navigateur vers `OFFICE_CENTRAL_LOGOUT_URL` avec le retour fixe `/logged-out`. `Office-Api` confirme/révise la session centrale avant de revenir sur la page publique `/logged-out`; cette page n’auto-login jamais.
