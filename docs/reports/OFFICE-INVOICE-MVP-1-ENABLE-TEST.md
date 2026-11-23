# OFFICE-INVOICE-MVP-1-ENABLE-TEST — session Office et contexte serveur

Date : 2026-11-23 (Europe/Paris)

## Résultat

`NEEDS_OPERATOR_IDENTITY_VERIFICATION`. L'authentification Office fonctionne pour l'opérateur selon sa déclaration et les releases sont en place, mais Codex ne peut pas accéder à cette session légitime. Aucun flag de recette ou d'écriture n'a été activé ; aucun formulaire n'a été soumis.

## Releases et état actuel

- Office : `office-invoice-mvp1-20261123-8150e7f`, source `office/main` `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- API/login : `api-invoice-mvp1-20261123-4f5baec`, source `Office-Api/main` `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`.
- SDK de facturation verrouillé : `devsys/shared` `b845450b2d4004fa31a5e3a540184fff42c7ebba`.
- Office flag `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`. API write gate et garde absents/OFF, allowlist vide, valeurs d'attente absentes. Diagnostics API et Office absents/OFF. Aucune surcharge de processus PHP-FPM observée.
- Aucune identité ou valeur privée n'a été lue dans les sessions PHP, exposée ou déduite d'une simple entrée de base.

## Chemin OIDC Office vérifié

`OidcSessionService` compare le `sub` et le `tenant_id` reçus via les claims OIDC/UserInfo vérifiés, puis place les valeurs de contexte dans la session PHP. `TenantContext` associe ensuite ce principal à une membership active. `OfficeAccessTokenProvider` fournit le bearer de la session utilisateur à `DevsysApiProvider`, puis au client SDK partagé. Les scopes affichés/stockés côté Office ne suffisent pas à prouver `write:invoices` : seule la réponse de l'API, qui valide le scope du bearer reçu, constitue la preuve pertinente.

Le flag d'écriture Office est OFF ; une création neuve pourrait donc emprunter `legacy->saveDraft()` et le SQL historique. Le formulaire ne doit pas être soumis tant que le mode API n'est pas effectivement activé et la protection API vérifiée.

## Action minimale pour établir la preuve

Les releases contiennent déjà le diagnostic opérateur non mutateur : `POST /Invoices/recipePreflight` (authentifié, CSRF), qui utilise la session OIDC courante et le SDK. L'API en aval vérifie le scope réel `write:invoices`, le principal utilisateur, le tenant effectif et la source serveur `devsys`; les réponses sont redacted. Les deux flags diagnostic étant OFF, le parcours n'a pas été lancé.

Un administrateur autorisé doit, en privé, valider le sujet et le tenant de l'identité OIDC existante via les outils OIDC/TenantContext établis (pas un nom/e-mail seul ni une ligne DB isolée), ainsi que l'accès au client fictif. Il peut ensuite ouvrir brièvement les seuls paramètres diagnostiques déjà déployés, avec attentes privées identiques, lease absolu <= 300 s et `BOOT_ID` courant, sans ouvrir les write gates. L'opérateur connecté déclenche lui-même `Invoices/recipePreflight` dans Office ; il ne transmet que `PASS`, `REFUSED` ou `UNAVAILABLE`. Puis les flags diagnostics sont immédiatement remis OFF et les attentes privées retirées.

Si le diagnostic retourne `PASS`, l'activation recette ultérieure doit garder les valeurs privées sur le serveur : garde API `User` + sujet/tenant exacts + `devsys`, garde placé avant le gate et refusant `/issue`; ensuite gate API et allowlist `devsys`, puis flag Office. Fermeture : gate API OFF et allowlist vide d'abord, Office OFF ensuite, garde OFF et retrait des attentes enfin. Une erreur, un résultat non concluant, un scope manquant ou une identité/tenant non attestée signifie aucun formulaire et aucun write flag ON.

## Résultat opérateur

Session Office : disponible selon la déclaration opérateur, mais pas pilotable par Codex. Diagnostic API depuis ce bearer : `NOT_RUN`. Scope, tenant et client fictif : `NOT_VERIFIED`. Création/PATCH : `NOT_RUN`. Aucun changement de configuration, aucun SQL, émission, numéro ou suppression.
