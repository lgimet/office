# OFFICE-INVOICE-MVP-ACTIVATION-SIMPLE

Date : 25 novembre 2026 (Europe/Paris)  
Verdict : **`ACTIVATED_PENDING_BUSINESS_RECETTE`**

## État installé

| Dépôt | `main` utilisé | Release effectivement servie |
| --- | --- | --- |
| `shared` | `93a0a8577094eb6f911cc54508eae14602399e15` | SDK embarqué dans Office au commit `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d` |
| `Office-Api` | `a115d5f7d36dd148f9d65296a7bb2151caa34cc1` | `api-office-mvp-pdf-20261125-a115d5f` |
| `office` | `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc` | `office-mvp-pdf-20261125-dfaa435` |

L'API a été construite depuis le `main` contenant le PDF ordinaire et le helper API-only en profil actif (PR #27 fusionnée). Les migrations 027/028 étaient déjà inscrites et présentes dans le schéma `office` avant l'intervention ; leurs SHA-256 vérifiés sont `db17f98228401a3ad5bf27ce76e59b2c5a5dc0169442eddaf5c76ac1e05e7be0` et `24d24bf602eed2de146eaf2da7fad97ce5afc3d8a9a84743cc1ae27d32efcd95`. Aucune migration ni écriture SQL n'a été exécutée pendant cette activation.

## Stockage et configuration PDF

Les deux paramètres auparavant absents ont été ajoutés à la configuration persistante API, sans modifier les autres valeurs :

- `INVOICE_PDF_ARCHIVE_DIR=/home/devsys/api/storage/invoice-pdf-archives`
- `INVOICE_PDF_WORK_DIR=/home/devsys/api/storage/invoice-pdf-work`

Les répertoires sont distincts, hors webroot et releases, `www-data:www-data`, mode `0700`, sans lien symbolique. Le worker `www-data` a réussi une vérification ponctuelle d'écriture/lecture/suppression dans chacun. Une copie protégée de l'ancienne configuration est conservée dans `/home/devsys/api-deploy/backups/office-mvp-pdf-20261125/api-env.before-pdf-paths` (root, mode `0600`). Les archives sont dans le stockage persistant `/home/devsys/api/storage`, inclus dans le périmètre `/home` du job de sauvegarde habituel ; aucune restauration n'a été testée.

## Construction et bascules

- API : candidat complet `api-office-mvp-pdf-20261125-a115d5f`, construit avec vendor depuis le lock, `composer validate --no-check-publish` et `composer check-platform-reqs --no-dev` réussis. Le helper a validé le profil `active`, les gates, les liens persistants et les migrations 026/027/028. `--dry-run` puis activation explicite ont réussi. Manifest SHA-256 : `b8b81aa0e86893133447712a82cf7710b3a625f5310e0184fb851ccc8a7f7f45`; `SHA256SUMS` : `f8c10dcd0e1fda2ac0ef69d7be93ad3171d8ae46c42fccc905155b67cdd244f3`.
- Office : release complète `office-mvp-pdf-20261125-dfaa435`, build depuis `main`, `composer install --no-dev` à partir du lock (aucun update), `npm ci`, `npm run build` et `composer check-platform-reqs --no-dev` réussis. Le `dist` Composer du SDK a répondu HTTP 404 ; l'installation de source a utilisé le SHA verrouillé exact. Le premier build Sass a échoué sur les propriétaires des assets extraits ; les propriétaires de la seule candidate inactive ont été corrigés, puis le build a réussi. SHA du lock Composer : `9fc4dbb63ccb5dcf97a85c391cf5edf275f62bbf81757d84b82371d787a95c4c`; SDK installé et verrouillé identiques (`d5606571c3a4d8a9fd67216ed87b3870e9f1a61d`). SHA du manifeste : `1acd63a8b4c33567bdb6cf57add5712fcedc1f6fc0ca1f58b4311953e66862e2`; SHA de `SHA256SUMS` : `b324c02fc70d439b3e386c743ce1cf401cd89b250008d9f81100aff9d2e9e5b3` (la configuration persistante et les chemins de données sont exclus).
- API avant : `api-invoice-facturx2-20261124-7f88c7c-r1`. Office avant : `office-invoice-facturx2-20261124-eb2f7aa`. Ces releases restent présentes pour retour arrière.
- L'API a été basculée par `deploy-api-only-release.py --profile active`; backup helper : `api-vhost-20261125T102208-1432434-api-office-mvp-pdf-20261125-a115d5f`.
- Office a été basculé par la procédure Office-only déjà utilisée : sauvegarde de `/etc/apache2/sites-enabled/office.devsys.fr.conf`, remplacement atomique des quatre chemins DocumentRoot/Directory puis mise à jour atomique de `current`. Backup vhost : `/home/devsys/office-deploy/backups/office-mvp-pdf-20261125-dfaa435-pre-switch/office.devsys.fr.conf.before`, SHA-256 `340929b6171fc5707ca000a79b1172ee1f48055547bd4a6d3d7c27eb54a25152`.

Les deux bascules ont passé `apachectl configtest` et `php-fpm8.4 -t`, puis ont nécessité un reload gracieux **global** d'Apache. Les fichiers de login n'ont pas été modifiés. Les gates sont restés inchangés : Factur-X API/Office ON, écritures API ON avec source `devsys`, lecture API Office ON et brouillons via API ON. `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED` reste ON ; aucun fallback SQL Office n'a été introduit.

## Contrôles post-bascule

| Contrôle | Résultat |
| --- | --- |
| API `/api/v1/health` en loopback | PASS — HTTP 200 JSON |
| Routes PDF API sans authentification | PASS — HTTP 401 |
| Office `/` | PASS — HTTP 302 vers le flux de connexion OIDC habituel |
| Route Office `/Invoices/0/pdf` sans session | PASS — HTTP 401 |
| Asset CSS Office servi | PASS — HTTP 200 |
| Découverte OIDC login en loopback après reload global | PASS — HTTP 200 ; configuration login non modifiée |
| Apache et PHP-FPM | PASS — actifs ; configtest réussi avant chaque reload |
| Intégrité du manifeste de la release Office | PASS — toutes les entrées vérifiées |
| Test authentifié de génération/téléchargement PDF | **NOT_RUN** — aucune facture de test de production n'était désignée/autorisé pour une écriture documentaire |

L'espace `/home/devsys` était de 8,3 Gio libres avant la préparation Office et d'environ 8 Gio après. Le dernier état consulté du job de sauvegarde indiquait succès, code 0, fin le 25/11/2026 à 03:26 CET ; le timer était actif et annonçait le 26/11 à 03:20. Aucune nouvelle sauvegarde ni restauration n'a été lancée.

Aucune facture, numérotation, séquence, snapshot, archive métier ou donnée SQL n'a été consultée ou modifiée. Aucun POST de génération, émission, ni téléchargement de facture réelle n'a été effectué. La recette navigateur authentifiée et le premier document PDF restent **`NOT_RUN`** ; le fonctionnement utilisateur complet n'est donc pas déclaré validé. Le PDF classique n'est pas un PDF/A ni une validation Factur-X, et aucune conformité juridique exhaustive n'est revendiquée.

## Retour arrière

- API : utiliser le rollback API-only documenté avec le backup `api-vhost-20261125T102208-1432434-api-office-mvp-pdf-20261125-a115d5f`, après configtest et approbation explicite du reload Apache global. Ne pas supprimer 027/028 ni les archives.
- Office : restaurer atomiquement le fichier vhost depuis le backup ci-dessus et remettre `current` vers `office-invoice-facturx2-20261124-eb2f7aa`, puis `apachectl configtest` et reload gracieux global. Ne pas supprimer les données persistantes ni l'ancienne release.
- Les chemins PDF ajoutés à l'`.env` API sont requis par la nouvelle API ; si le code API est rollbacké, ils peuvent rester sans effet. Ne pas restaurer l'ancien `.env` automatiquement.

## Verdict et prochaine action

**`ACTIVATED_PENDING_BUSINESS_RECETTE`** : les releases API et Office sont servies, les migrations préexistantes sont cohérentes, le stockage privé est accessible au worker et les vérifications HTTP sans mutation passent. La génération métier reste à valider uniquement avec une facture de test explicitement autorisée, via une session utilisateur tenantée ; aucune facture réelle existante ne doit servir de test.
