# Mise en production OIDC Office

1. Provisionner `office-web` dans `Office-Api` avec la redirect URI exacte `https://office.devsys.fr/auth/oidc/callback` et les scopes nécessaires.
2. Copier le secret uniquement dans l’environnement serveur (`OFFICE_OIDC_CLIENT_SECRET=` dans les exemples).
3. Définir issuer, client, redirect URI, scopes et resource dans `.env`.
4. Vérifier discovery/JWKS, login, callback, UserInfo et les opérations ClientsApi.
5. Vérifier cookie host-only, absence de token dans HTML/logs, expiration effective du cookie au logout, `session.gc_maxlifetime` aligné sur `OFFICE_SESSION_TTL` et ré-auth après expiration.
6. Configurer dans `Office-Api` l’allowlist exacte `https://office.devsys.fr/logged-out`, ainsi que `OFFICE_CENTRAL_LOGOUT_URL` et `OFFICE_CENTRAL_RP_LOGOUT_URL` sur la même origine que l’issuer (`/auth/logout` et `/auth/logout/rp`), puis vérifier le parcours POST logout Office → POST logout central navigateur → `/logged-out`.
7. Pour 7C.3, basculer ensemble `OFFICE_OIDC_ISSUER` et `OFFICE_CENTRAL_LOGOUT_URL` vers `https://login.devsys.fr`; conserver `OFFICE_OIDC_RESOURCE` et `DEVSYS_API_BASE_URL` sur `https://api.devsys.fr`.

Les factures, company settings et autres agrégats encore lus directement en base ne sont pas migrés. Un tenant Office ne doit donc pas être basculé en stockage `dedicated` en production normale tant que ces agrégats n’utilisent pas le même routage.
