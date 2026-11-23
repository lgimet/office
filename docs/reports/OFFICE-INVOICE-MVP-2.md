# OFFICE-INVOICE-MVP-2 — Émission des brouillons API depuis Office

Date : 2026-11-23 (Europe/Paris)

## Statut

`MVP2_DEPLOYED_READY_FOR_USER_ISSUE` — code fusionné et release Office active. Aucun appel d’émission n’a été effectué par Codex. Le brouillon API #4 n’a été ni lu ni modifié pendant ce déploiement ; il reste en attente du clic explicite de l’opérateur, conformément à l’état connu avant ce lot.

## Références

- Office `main` de départ : `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- Office-Api `main` relu : `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e` ; aucune modification API nécessaire.
- SDK `devsys/shared` inchangé et verrouillé par Office au commit `b845450b2d4004fa31a5e3a540184fff42c7ebba` ; son `InvoicesApi::issue()` existant est utilisé.
- Releases observées avant intervention : API/login `api-invoice-mvp1-20261123-4f5baec`, Office `office-invoice-mvp1-20261123-8150e7f`.
- Branche de code `feature/office-invoice-mvp-2`, HEAD `4d01c589f1a665309d04f6ffa48ac0b6f8bd50aa`.
- PR [#11](https://github.com/lgimet/office/pull/11), fusionnée le 2026-11-23 à 17:27 UTC ; merge commit et `main` final `86df3876b2a84507e8c82cf9e1e1ad2cabdc80de`.

## Changements Office

- `DevsysInvoiceDraftService` vérifie par GET la provenance et l’état. Seuls les brouillons API `devsys` sans numéro peuvent être émis depuis Office ; les origines `spotcard`, `stationid` et `menuflow` restent refusées. Les factures historiques gardent leur chemin existant. Une erreur API sur une facture API ne déclenche aucun fallback SQL.
- `InvoiceDraftApiInterface` / `SharedInvoiceDraftApi` délèguent à `shared/InvoicesApi::issue()`.
- La clé d’idempotence est créée côté serveur dans la session, liée à la session/utilisateur/tenant et à l’ID de facture ; le même contexte est réutilisé après rechargement et reprise d’une tentative incertaine. Si le GET trouve déjà la facture émise, Office affiche le numéro existant sans appeler de nouveau `/issue`.
- Le bouton d’émission est affiché uniquement pour un brouillon API `devsys` lorsque le mode API est actif. La confirmation précise que l’émission est définitive et porte sur la dernière version enregistrée. L’action API n’envoie que l’ID et le contexte serveur opaque, jamais les champs de facture ni des sélecteurs d’autorité du navigateur.
- Les réponses d’erreur de date (`ISSUE_DATE_MUST_BE_TODAY`), de configuration d’émetteur et de réconciliation de numérotation sont expliquées sans changer la date ou la configuration automatiquement.
- Après émission, l’action API ne permet plus édition, suppression ou nouvelle émission. La consultation récupère les instantanés client, émetteur, montants et lignes de l’API. Le PDF est masqué pour les factures API tant qu’un rendu fidèle basé sur ses snapshots/template versionné n’est pas pris en charge. Les vues SQL historiques restent inchangées.

## Validations

- `composer test` : PASS, 171 tests / 517 assertions.
- `composer validate --no-check-publish` : PASS avec l’avertissement préexistant d’absence de licence.
- `composer check-platform-reqs --no-dev` : PASS sous PHP 8.4.24.
- `composer audit --locked --no-dev` : PASS, aucun avis de vulnérabilité.
- `npm run build` : PASS ; les assets CSS compilés n’ont pas été modifiés.
- `node --check public/assets/js/Object/Invoices.js`, lint PHP des six fichiers PHP touchés et `git diff --check` : PASS.
- Tests d’émission réels, accès à la base, POST/PATCH `/issue`, numéro légal et recette de la facture #4 : `NOT_RUN`.

## Configuration et limites

Les flags déjà configurés avant ce lot n’ont pas été modifiés. Le gate API `INVOICE_WRITES_ENABLED` est commun aux écritures API : lorsqu’il est ouvert, l’API peut accepter `/issue` pour tout principal/source déjà autorisé par ses contrôles normaux. Ce lot n’ajoute pas de verrou API général et ne prétend pas bloquer les autres clients autorisés. Office, lui, ne propose l’émission qu’aux brouillons `devsys` et l’API vérifie encore le tenant, la source, le scope, l’état brouillon, l’idempotence, l’émetteur et la date.

L’émission réelle attribue un numéro légal définitif. Elle n’a pas été déclenchée par Codex. L’opérateur devra vérifier le brouillon et cliquer explicitement sur « Émettre la facture » après mise en service ; si l’API signale une date différente du jour Europe/Paris, la date devra être corrigée explicitement avant une nouvelle tentative. Aucun numéro ne doit être réinitialisé ou recyclé.

## Déploiement Office

- Déploiement le 2026-11-23 entre 18:30 et 18:33 (Europe/Paris), sans toucher à l’API/login.
- Release active : `office-invoice-mvp2-20261123-86df387`, copie complète de `office-invoice-mvp1-20261123-8150e7f` avec overlay des seuls fichiers runtime Office listés ci-dessus. Archive `/home/devsys/office-deploy/artifacts/office-invoice-mvp2-20261123-86df387.tar.gz`, SHA-256 `04e4a5d0d2b8a56d99ef9f760975a8cd500f770407009211b852ec4bdc705125`. Archive et `SHA256SUMS` vérifiés.
- `composer.lock` SHA-256 `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`; `devsys/shared` verrouillé et installé au SHA `b845450b2d4004fa31a5e3a540184fff42c7ebba`. Vendor inchangé, PHP-FPM 8.4.
- Liens externes conservés : `.env` → `/home/devsys/office/.env`; `var/cache` → `/home/devsys/office/var/cache`. Aucun contenu `.env` ni secret n’est dans l’archive. Aucun flag ni fichier d’environnement n’a été modifié.
- `current` et les quatre chemins `DocumentRoot`/`<Directory>` du vhost actif `/etc/apache2/sites-enabled/office.devsys.fr.conf` pointent vers `/home/devsys/office-deploy/releases/office-invoice-mvp2-20261123-86df387/public`. Directives TLS, autres vhosts, vhost `sites-available`, API et login inchangés.
- Sauvegarde vhost : `/home/devsys/office-deploy/backups/office-invoice-mvp2-20261123-86df387-pre-switch/office.devsys.fr.conf.before`, SHA-256 `a1d7b6cd7e5d208abc7bf5e32718ec3ed30c6b68dd6a10c7fa49563248a2332d`. `current` précédent : `/home/devsys/office-deploy/releases/office-invoice-mvp1-20261123-8150e7f`.
- Une première activation a été annulée car le contrôle comparait le SHA HTTP du JavaScript au fichier source. Le contrôleur existant `JsController` ajoute un horodatage à chaque réponse, donc cette comparaison était invalide. Le vhost et `current` ont été restaurés, puis la bascule a été reprise avec vérification du marqueur fonctionnel servi sans cache. L’état final est cohérent. Au total, Apache a reçu trois graceful reloads : activation initiale, rollback de validation, activation finale. Aucun reload/restart PHP-FPM.
- Après activation finale : `apachectl configtest` PASS ; `php-fpm8.4 -t` PASS ; Apache et PHP-FPM actifs. GET : Office `/` 302 ; dashboard CSS 200 ; `/Invoices/data` anonyme 401 ; JavaScript facture contient le marqueur de la release ; API health 200 et invoices anonymes 401 ; OIDC discovery, JWKS et login 200 ; témoins SpotCard et StationID 302, MenuFlow 200. Aucun POST/PATCH métier envoyé.
- API `current` est resté sur `api-invoice-mvp1-20261123-4f5baec`. L’émission, la date d’émission, l’émetteur, le numéro et la lecture authentifiée de la facture #4 n’ont pas été testés. Le clic d’émission reste une action opérateur explicite.

## Limites opérationnelles et rollback

Le gate API d’écriture est commun aux opérations permises par l’API : il ne devient pas « create/PATCH seulement » par ce lot. Office n’expose l’émission que pour une facture `devsys`, mais les autres clients API conservent les contrôles existants. Office indique les erreurs de date Europe/Paris, d’émetteur et de numérotation sans correction automatique. Le PDF des factures API est masqué jusqu’à la disponibilité d’un rendu fidèle fondé sur les snapshots et le modèle versionné.

Rollback Office : restaurer le vhost sauvegardé ci-dessus et le lien `current` vers la release MVP-1, valider avec `apachectl configtest`, puis effectuer un graceful reload Apache. Aucune restauration de base n’est prévue. Les deux releases et l’archive restent conservées.
