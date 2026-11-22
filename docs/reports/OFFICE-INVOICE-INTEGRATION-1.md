# OFFICE-INVOICE-INTEGRATION-1 — Matrice et livraison Office

**Date :** 22 novembre 2026
**Décision : `READY_FOR_INTEGRATION_REVIEW` — code/SDK verrouillé et PR Office en brouillon ; aucun merge ni déploiement.**

## Références et branches

| Dépôt | Base | Source intégrée / SHA vérifié |
|---|---|---|
| Office-Api | `main` `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` | `feature/office-api-invoice-numbering-1.1` `c9df8851d7d1f6d96ac185101b478d2e63862148` |
| shared | `main` `83e5b7da32ae20039218586e18dc93ee9ad95852` | `feature/office-api-business-reference-1.1` `0661b92b914c28c41f45f5bd19bb9a860c232676` |
| Office | `work/office-deploy-php84-2.2` `1333e34aac9f1738afaf795efdcb77044be163e9` | `feature/office-inv-recette-1.2` `8889b5b9dc20c442f0cf105540f2e95db9b443a9` après verrouillage Composer |

Les refs distantes ont été relues. Office PR brouillon [#2](https://github.com/lgimet/office/pull/2) cible la branche fonctionnelle 2.2, et non `main`, car son parent apporte déjà le parcours UI/PHP 8.4 requis. La base 2.2 doit être intégrée séparément à `office/main` avant une PR de convergence vers `main`. Après mise à jour de sa branche, l'ascendance Git locale est `0 behind / 4 ahead` et `git merge-tree` ne signale aucun conflit. Le premier instantané GitHub avait `mergeable=false`; après rafraîchissement de la PR, son état courant est `mergeable=true`. Aucun merge automatique.

## Matrice de compatibilité

| Couche | SHA / contrat | Compatibilité vérifiée |
|---|---|---|
| Office-Api | `c9df8851…`; routes create, PATCH, GET/list, issue ; 024 additive | champ métier nullable, omission PATCH préserve, `null` efface, ETag/idempotence conservés ; issuer serveur; émission utilise mapping et séquence légale |
| shared | `0661b92b…` | DTO create/update/detail ; PATCH omission/effacement explicite ; signatures SDK préservées |
| Office | nouvelle tête de branche après ce lot | réutilise `InvoicesApi`, services et hooks existants ; aucune URL HTTP ni SQL facture ajoutée |

Office n'appelle pas l'émission dans le parcours brouillon. Les brouillons restent sans numéro légal. La date passée/future est acceptée pour un brouillon ; le refus `ISSUE_DATE_MUST_BE_TODAY` concerne une émission ultérieure. Tenant, source et issuer ne sont jamais définis par le navigateur. Issue/delete restent fermés pour les brouillons API.

## Dépendance Composer Office

Le changement ciblé de packaging remplace le dépôt `path ../shared` symlink mutable par le dépôt VCS HTTPS `lgimet/shared.git`, avec `no-api` et installation `source` pour `devsys/shared` seulement ; les autres packages restent `dist`. La contrainte Composer demande la branche 1.1, mais `composer.lock` suivi fixe le `source.reference` à `0661b92b914c28c41f45f5bd19bb9a860c232676`. Le lock calcule 52 packages ; son SHA-256 est `ce2ba7dea44f6a18b5b9d83eddb6b5e490f13aa8c959d9dc8e2dadd415a40676`.

Une installation testée avec cache et `vendor` distincts, depuis le lock, a installé le SDK à ce SHA dans un répertoire réel (`vendor/devsys/shared` non symlink). Le dépôt Composer de shared retourne 404 sur l'archive dist ; le clone VCS HTTPS fonctionne. L'installation reproductible requiert donc un accès Git de lecture pour le builder, ou un futur artefact vendeur versionné avec manifeste SHA. L'environnement runtime déployé n'a pas été interrogé ; ce lock ne prouve pas encore le contenu actuellement servi.

Le flux de développement local par symlink `../shared` est remplacé dans cette branche ; développer le SDK demande maintenant la mise à jour explicite du commit/lock. Cela évite le faux positif local et rend le candidat testable exactement sur le SDK retenu. Aucun accès privé ni secret n'est inclus.

## Contrôles réellement exécutés

En clones isolés, PHP CLI 8.4.24, sans DB, HTTP réel ou secret de production :

| Dépôt | Résultat |
|---|---|
| Office-Api | `composer validate` PASS (avertissement licence absente), `composer check-platform-reqs` PASS, `composer test` PASS — 235 tests / 802 assertions ; 15 MySQL SKIPPED ; `php -l` 8 fichiers PASS |
| shared | `composer validate` PASS, `composer check-platform-reqs` PASS, `composer test` PASS — 48 tests / 205 assertions ; `php -l` 4 fichiers PASS |
| Office | `composer validate` PASS (avertissement licence absente), `composer check-platform-reqs` PASS, installation verrouillée propre PASS, `composer test` PASS — 138 tests / 384 assertions, `php -l` 4 fichiers PASS |
| Office assets | `node --check` PASS, `npm ci` PASS (17 packages), `npm run build` PASS ; CSS suivis inchangés, SHA dashboard `18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f`, login `b119e224cb980478c598a8b8176f5e4db94d58f679313d2a36bff07ef515ef09` |

Un `git diff --check` global contre les bases relève seulement des espaces finaux dans les en-têtes Markdown des rapports fonctionnels préexistants `...NUMBERING-1.1.md` et `...RECETTE-1.2.md`; ces lignes antérieures n'ont pas été réécrites. Le diff des changements d'intégration lui-même est vérifié sans erreur. La migration 024, les 15 tests MySQL, l'API runtime, les flags runtime, l'OIDC HTTP authentifié et toute écriture sont `NOT_RUN`/non observés.

## PRs et ordre de mise à disposition

1. PR brouillon [Office-Api #3](https://github.com/lgimet/Office-Api/pull/3) → `main`; revue du code puis intégration. La nouvelle API sélectionne `invoices.business_reference` dans ses GET : appliquer et contrôler la migration 024 par opération distinctement approuvée **avant** le basculement du code API. Si 024 n'est pas confirmée, ne pas déployer cette version API, même écritures OFF.
2. PR brouillon [shared #3](https://github.com/lgimet/shared/pull/3) → `main`; revue, puis publication ultérieure d'un tag immuable. Le lock Office fixe déjà le SHA feature qui a été testé ; migrer vers le tag seulement après sa publication vérifiée.
3. PR Office [#2](https://github.com/lgimet/office/pull/2) mise à jour → `work/office-deploy-php84-2.2`. Intégrer la base 2.2 vers `main` par une PR séparée, puis décider de la convergence Office.
4. Construire une release Office depuis le lock sous PHP 8.4 avec `composer install --no-interaction` (pas `update`), builder pouvant lire le dépôt shared. Garder `.env` et données hors artefact, conserver la release antérieure, vérifier en HTTP lecture seule lors d'une fenêtre autorisée.
5. Les flags de brouillon Office et d'écriture API restent OFF. Migration 024 + mapping issuer, garde tenant/principal et restauration isolée restent des opérations distinctes. Une recette d'écriture future exige un GO distinct ; issue/delete, paiement, webhook, email et export comptable sont exclus.

## État opérationnel

Aucun état de production n'a été lu dans ce lot. Valeurs effectives des flags/migrations runtime : **non observées** ; aucune activation, modification de scope, migration, déploiement, émission ou suppression n'a eu lieu. La déclaration opérateur antérieure de six contrôles manuels Office reste une déclaration, pas une preuve HTTP reproduite.
