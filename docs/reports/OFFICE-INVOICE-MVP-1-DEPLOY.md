# OFFICE-INVOICE-MVP-1 — Déploiement Office

Date : 2026-11-23 (Europe/Paris)

## Décision

`MVP1_DEPLOYED_RECIPE_PENDING` — les changements API et Office sont fusionnés et déployés. La recette authentifiée d'un brouillon fictif n'a pas été exécutée : aucune session opérateur exploitable ni contexte privé serveur validé n'était disponible pour cette opération. Les flags d'écriture sont restés OFF.

## Références et fusions

- Dépôt : `lgimet/office`.
- `main` avant intégration : `34646a5229f350c3bcd5a9fe120eb9e48d3840ea`.
- PR [#9](https://github.com/lgimet/office/pull/9), fusionnée le 2026-11-23 à 14:48:37 UTC après l'API ; commit de merge et `main` final : `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- La branche `feature/office-invoice-mvp-1` a été supprimée après fusion ; son historique est conservé dans `main`.
- Dépendance `devsys/shared` verrouillée au commit `b845450b2d4004fa31a5e3a540184fff42c7ebba` ; `composer.lock` SHA-256 `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`. Le paquet installé dans le candidat a été vérifié à ce commit, sans symlink mutable.
- API déployée en premier : `api-invoice-mvp1-20261123-4f5baec`, SHA source `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`.

## Release mise en service

- Release : `office-invoice-mvp1-20261123-8150e7f` ; source Office `main` SHA `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- Archive : `/home/devsys/office-deploy/artifacts/office-invoice-mvp1-20261123-8150e7f.tar.gz`.
- SHA-256 vérifié : `7b73adb1fb0a33eed9b673ea847cbca2eddd44d0c0c23c4fd8ac2667639e9b8a` ; `SHA256SUMS` revérifié après bascule.
- Le candidat est une copie complète de la release Office active avec overlay des changements fusionnés. Les seuls fichiers runtime de code remplacés étaient `src/Services/DevsysInvoiceDraftService.php` et `src/Views/Invoices/form.twig`. Le `composer.lock`, vendor, stockage, cache et configuration persistante ont été conservés.
- `current` et le vhost Office pointent vers `/home/devsys/office-deploy/releases/office-invoice-mvp1-20261123-8150e7f/public`.
- Sauvegarde vhost : `/home/devsys/office-deploy/backups/office-invoice-mvp1-20261123-8150e7f-pre-switch/office.devsys.fr.conf.before`, SHA-256 `a1d7b6cd7e5d208abc7bf5e32718ec3ed30c6b68dd6a10c7fa49563248a2332d`.
- Le diff vhost ne change que les quatre chemins absolus `DocumentRoot`/`<Directory>`. `apachectl configtest` a réussi, suivi d'un graceful reload Apache ; pas de reload/restart PHP-FPM. API/login n'ont pas été modifiés lors du switch Office.

## Vérifications

Tests sur le code fusionné en worktree isolé :

- `composer test` : PASS, 167 tests, 490 assertions.
- `composer validate --no-check-publish`, `composer check-platform-reqs --no-dev`, `composer audit --locked --no-dev`, `npm ci`, `npm run build`, `node --check public/assets/js/Object/Invoices.js`, lint PHP et `git diff --check` : PASS.
- Vérifications SQL d'intégration : `NOT_RUN` ; aucun DSN production, aucune migration, aucun SQL métier.
- Après bascule : Apache configtest et `php-fpm8.4 -t` PASS ; services Apache et PHP-FPM actifs.
- GET : accueil Office 302 (redirige vers le flux habituel), `/assets/css/dashboard.css`, `/assets/css/objects/Invoices/form.css` et `/assets/js/Object/Invoices.js` 200, `/Invoices/data` sans session 401, GET `/Invoices/recipePreflight` 405 (route POST-only ; aucun POST envoyé). API health, OIDC discovery, JWKS et page login 200 ; factures sans bearer 401 ; diagnostic API désactivé 404.
- Hôtes témoins en GET : `my.spotcard.fr` 302, `my.stationid.fr` 302, `menuflow.fr` 200. Ces codes ont été observés après la bascule ; les 302 des deux premiers correspondent aux réponses constatées, sans assertion préalable qu'ils devaient être 200.
- Les logs Office comportaient trois entrées Apache `AH01071` de type message PHP dans la fenêtre de vérification ; aucun contenu sensible n'est reproduit. Les sondes GET n'ont observé aucun 5xx et aucun marqueur `PHP Fatal error`/`Uncaught` n'a été compté sur ces entrées. Une corrélation détaillée de ces messages n'a pas été nécessaire à la validation des probes et n'est pas revendiquée.

## Flags et recette

- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false` ; `OFFICE_INVOICE_PREFLIGHT_ENABLED` absent/OFF.
- API : `INVOICE_WRITES_ENABLED` absent/OFF, `INVOICE_WRITE_SOURCES` absent/vide, `INVOICE_DRAFT_RECETTE_GUARD_ENABLED` absent/OFF ; diagnostic absent/OFF.
- Aucune surcharge de ces clés observée dans l'environnement des processus PHP-FPM au contrôle post-bascule.
- Aucun formulaire de facture soumis, aucun POST/PATCH/issue/delete, aucune émission ni numéro légal, aucun client créé.
- Recette de création et modification : `NOT_RUN`. Aucun ID de facture n'a été créé. Une session Office authentifiée de l'opérateur et les attentes privées sujet/tenant côté API ne sont pas accessibles à Codex et n'ont pas été configurées. Il n'y a donc pas de preuve de session métier ni de client test vérifié par ce déploiement.
- SpotCard inter-source : `NOT_RUN`, faute de client OAuth de test vérifié ; aucune usurpation de source.

## Comportement livré et suite

Office route les brouillons API par le service → couture SDK → `shared/InvoicesApi`, et n'utilise pas SQL historique en secours pour un brouillon reconnu comme API en cas d'échec API. L'édition de brouillons d'autres sources dépend de l'éligibilité retournée par l'API pour le principal Office et le tenant serveur. Les factures historiques SQL conservent leurs règles existantes. L'émission reste indisponible dans Office pour les brouillons API.

Une recette ultérieure nécessite une autorisation et une session opérateur réelles, la confirmation privée du sujet/tenant et du client fictif existant, ainsi qu'une fenêtre de write limitée où le garde serveur refuse tout autre principal/tenant et interdit `issue`. Aucune écriture ne doit être ouverte tant que ces conditions ne sont pas établies. Le brouillon de test, s'il est créé dans un lot futur, ne devra pas être émis ou supprimé dans le présent lot.
