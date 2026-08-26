# Mise en production OIDC Office

1. Provisionner `office-web` dans `Office-Api` avec la redirect URI exacte `https://office.devsys.fr/auth/oidc/callback` et les scopes nécessaires.
2. Copier le secret uniquement dans l’environnement serveur (`OFFICE_OIDC_CLIENT_SECRET=` dans les exemples).
3. Définir issuer, client, redirect URI, scopes et resource dans `.env`.
4. Vérifier discovery/JWKS, login, callback, UserInfo et les opérations ClientsApi.
5. Vérifier cookie host-only, absence de token dans HTML/logs, expiration effective du cookie au logout, `session.gc_maxlifetime` aligné sur `OFFICE_SESSION_TTL` et ré-auth après expiration.
6. Configurer dans `Office-Api` l’allowlist exacte `https://office.devsys.fr/logged-out`, ainsi que `OFFICE_CENTRAL_LOGOUT_URL` et `OFFICE_CENTRAL_RP_LOGOUT_URL` sur la même origine que l’issuer (`/auth/logout` et `/auth/logout/rp`), puis vérifier le parcours POST logout Office → POST logout central navigateur → `/logged-out`.
7. En cas d’échec du callback OIDC, vérifier le parcours Office `callback` → journal serveur → contexte d’erreur de session court → `/auth/error`; aucune `error_description` du fournisseur ne doit apparaître dans l’URL ou la page. Les erreurs générées directement par `login.devsys.fr` restent rendues par ce serveur.
7. Pour 7C.3, basculer ensemble `OFFICE_OIDC_ISSUER` et `OFFICE_CENTRAL_LOGOUT_URL` vers `https://login.devsys.fr`; conserver `OFFICE_OIDC_RESOURCE` et `DEVSYS_API_BASE_URL` sur `https://api.devsys.fr`.

Le socle multi-tenant rattache désormais les factures, company settings et séquences aux tenants via la migration API `018_add_office_multi_tenant_foundation.sql`. Cette migration doit être exécutée avant d’activer le routage tenant en production; aucun stockage `dedicated` n’est introduit par Office.
