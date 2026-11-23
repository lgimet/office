# OFFICE-INVOICE-MVP-2 — Émission des brouillons API depuis Office

Date : 2026-11-23 (Europe/Paris)

## Statut

`MVP2_CODE_READY_DEPLOY_PENDING` — raccordement Office implémenté et testé en isolation. Aucune facture n’a été émise. Le brouillon API #4 reste en attente du clic explicite de l’opérateur.

## Références

- Office `main` de départ : `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- Office-Api `main` relu : `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e` ; aucune modification API nécessaire.
- SDK `devsys/shared` inchangé et verrouillé par Office au commit `b845450b2d4004fa31a5e3a540184fff42c7ebba` ; son `InvoicesApi::issue()` existant est utilisé.
- Releases observées avant intervention : API/login `api-invoice-mvp1-20261123-4f5baec`, Office `office-invoice-mvp1-20261123-8150e7f`. Aucun pointeur `current`, vhost ou `.env` n’a été modifié dans la phase code.
- Branche de travail : `feature/office-invoice-mvp-2` (SHA à renseigner après commit).

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

## Déploiement

`NOT_RUN` à cette étape. La release Office et son archive versionnée seront préparées et déployées après intégration du commit dans `main`, en conservant la release précédente pour rollback. Le déploiement n’effectuera aucun appel d’émission et ne modifiera pas les flags.
