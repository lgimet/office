# OFFICE-INVOICE-FACTURX-1 — saisie des données préalables à l’émission

Date : 2026-11-23  
État : **IMPLEMENTED_NOT_DEPLOYED**

## Références

- Base Office : `main` `174adb1e1ec4cc56c8062cbd0082f385912aed8a`.
- Branche `feature/office-invoice-facturx-1`, tête `201d0960558b38045f76d0d2f388b3409c7512c6`; commit code `c288887bbbf959a919ad3e6331a440c31c2cc4d1`; PR brouillon [#13](https://github.com/lgimet/office/pull/13).
- Base SDK déjà épinglée avant ce changement : `b845450b2d4004fa31a5e3a540184fff42c7ebba`.
- SDK étendu utilisé par le candidat : `shared` `d2b52a83a7265b4fa158090549462ec72c26ad45`, épinglé dans `composer.lock` (pas de référence flottante).
- Release Office constatée en lecture seule : `office-invoice-mvp2-20261123-86df387`.

## Changements

Le service Office transmet désormais `unit_code` et `tax_category_code` par `shared/InvoicesApi`; les réponses de lecture les restituent au formulaire. Le formulaire conserve les contrôles et sauvegardes existants, autorise toujours les brouillons incomplets et présente deux sélecteurs explicites par ligne : unité normalisée (`C62`, `HUR`, `DAY`, `LS`, `MON`) et catégorie TVA (`S`, `Z`). Il n’infère jamais la catégorie à partir du taux.

Un message d’émission dédié indique de compléter, dans la fiche Clients, le nom et l’adresse de facturation, le code postal, la ville et le code pays ISO à deux lettres. Les erreurs de catégorie/unité indiquent les corrections à faire sur les lignes. Aucun accès SQL ni appel HTTP direct n’a été ajouté. Pas de modification des factures émises ou de l’architecture OIDC.

## Vérifications

- `composer test` : **171 tests, 519 assertions**, PASS.
- `npm run build` : PASS. (Le script construit les feuilles générales; le CSS de formulaire factures est livré dans son fichier d’objet existant.)
- PHP lint des fichiers touchés : PASS. `composer validate --no-check-publish` : valide, avertissement de licence absent déjà présent. `composer audit --locked --no-dev` : aucun avis.
- `git diff --check` : PASS.
- Aucun déploiement, aucune modification de `.env`, aucune facture créée/émise et aucune migration exécutée.

## Dépendance et suite

La branche SDK doit être intégrée ou conservée comme commit immuable accessible avant packaging Office. La release Office devra embarquer exactement ce commit via le lock. Le nouveau schéma API nécessite la migration additive 025. Après déploiement coordonné API puis Office, les fiches clients incomplètes et les lignes non catégorisées seront refusées uniquement à l’émission; les brouillons restent modifiables.
