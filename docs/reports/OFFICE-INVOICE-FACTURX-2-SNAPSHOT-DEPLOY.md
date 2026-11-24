# OFFICE-INVOICE-FACTURX-2-SNAPSHOT-DEPLOY

Date : 24 novembre 2026 (Europe/Paris)  
Statut : `DEPLOYED`

## Fusion et versions

- shared PR [#11](https://github.com/lgimet/shared/pull/11) fusionnée ; `shared/main` : `bae7a5d62ee815bc5535a869546658067f9e1aee`.
- Office PR [#18](https://github.com/lgimet/office/pull/18) fusionnée après shared ; `office/main` : `eb2f7aa63486ece3d2d5f7637e1def56c835b1bb`.
- Office-Api inchangé : release active `api-invoice-facturx2-20261124-7f88c7c-r1`.
- SDK `devsys/shared` dans le lock et le vendor de la release Office : commit immuable `ab5ba633dcb3247bb7f6751f0801bd4fca97da77`. La PR shared apporte un test et un rapport ; elle ne change pas le code runtime du SDK.

## Release Office

- Release active : `office-invoice-facturx2-20261124-eb2f7aa`, source `eb2f7aa63486ece3d2d5f7637e1def56c835b1bb`.
- Artefact complet : `/home/devsys/office-deploy/artifacts/office-invoice-facturx2-20261124-eb2f7aa.tar.gz`.
- SHA-256 artefact : `7a0027236412ef0b97bbfede33a4439375a09c26a627f05fd97c8a776ac679a5`.
- `current` et les quatre chemins DocumentRoot/`<Directory>` du seul vhost Office pointent sur cette release. Le vhost a été sauvegardé avant la bascule sous `/home/devsys/office-deploy/backups/office-invoice-facturx2-20261124-eb2f7aa/`.
- Le vhost Office a été modifié puis `apachectl configtest` réussi ; un reload gracieux Apache a été effectué. Apache relit globalement sa configuration, y compris le vhost login, mais aucun fichier ni réglage login/OIDC n'a été modifié. PHP-FPM n'a pas été rechargé/redémarré.
- Aucun flag ni fichier `.env` n'a été modifié. Avant/après : Office Factur-X `true`, API Factur-X `true`, brouillons API `true`, lecture API `true` ; API écritures `true`, sources `devsys`.

## Vérifications

- Shared `composer test` : PASS, 54 tests / 224 assertions.
- Office `composer test` : PASS, 173 tests / 529 assertions.
- Office `npm ci` depuis le lock : PASS (17 paquets, 0 vulnérabilité signalée) ; `npm run build` : PASS. Les CSS produits correspondent à la release précédente.
- `composer validate --no-check-publish` : valide, avertissement existant sur la licence non déclarée.
- `composer install --no-dev --no-interaction` dans la candidate : PASS, aucune installation/mise à jour nécessaire ; plateforme PHP 8.4.24 PASS.
- Lint des deux fichiers PHP touchés, `git diff --check`, `apachectl configtest`, `php-fpm8.4 -t` : PASS.
- Vérifications loopback sans session : Office `/` 302, `/Invoices/data` 401 ; API santé 200, liste sans jeton 401. Apache et PHP-FPM actifs.
- Le message métier précis est couvert par le test unitaire à erreur simulée. Aucun navigateur avec session opérateur n'était disponible ; l'affichage authentifié n'a donc pas été vérifié en production.

## Limites et absence de mutation métier

Aucune création, émission, génération ou modification de facture/client n'a eu lieu ; aucune requête de génération n'a été envoyée pour `2026-0003`. Aucune donnée SQL, migration, release API, séquence ou snapshot n'a été touché. La correction porte uniquement sur la présentation sûre des champs manquants transmis dans `FACTURX_SNAPSHOT_INCOMPLETE`. Le lot ISSUE-POLICY reste séparé.

## Retour arrière Office

L'ancienne release conservée est `office-invoice-facturx2-20261124-c08b558`. Pour rollback : repointer atomiquement `/home/devsys/office-deploy/current` vers cette release, restaurer le vhost depuis la sauvegarde ci-dessus, valider avec `apachectl configtest`, puis effectuer un reload gracieux Apache. Ne pas modifier l'API, les données ni les flags.
