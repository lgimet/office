# OFFICE-INVOICE-MVP-1 — Recette Office du brouillon API

Date : 2026-11-23 (Europe/Paris)

## État

`SERVER_READY_FOR_OPERATOR` — le parcours livré est prêt à être exercé après validation privée du contexte serveur. Aucun flag n'a été ouvert ; aucune facture n'a été créée ou modifiée. La tentative précédente était bloquée avant mutation (`RECIPE_BLOCKED` / `RECETTE_BLOCKED`) et n'a produit aucune écriture.

## Runtime et configuration constatés

- Release active : `office-invoice-mvp1-20261123-8150e7f`, `office/main` `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- API active : `api-invoice-mvp1-20261123-4f5baec`, `Office-Api/main` `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`.
- SDK verrouillé et physiquement installé : `devsys/shared` `b845450b2d4004fa31a5e3a540184fff42c7ebba`.
- Flag Office `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`. API `INVOICE_WRITES_ENABLED` absent/OFF, `INVOICE_WRITE_SOURCES` vide, garde de recette absent/OFF. Les attentes privées du garde sont absentes. Les processus FPM observés n'ont pas d'override de ces clés.
- GET anonymes observés : Office accueil 302 (flux habituel), données facture 401, API santé 200, OIDC 200, facture API 401 sans bearer.

## Parcours Office et précaution SQL

Le parcours API est Office → `DevsysInvoiceDraftService` → adaptateur/SDK `shared/InvoicesApi` → API. Une fois en mode API, création et modification passent par l'API ; une facture reconnue comme API ne retombe pas sur SQL historique si l'appel API échoue. **À l'état actuel, le flag est OFF et une création neuve peut utiliser `legacy->saveDraft()` (SQL local).** Ne pas soumettre le formulaire avant que le flag API soit réellement activé et vérifié.

La session OIDC de l'opérateur, son scope effectif `write:invoices`, son tenant côté serveur et la présence du client fictif dans ce tenant ne sont pas accessibles à Codex. Aucune donnée d'identité ou de client n'a été consultée. L'opérateur doit valider ces éléments côté serveur sans transmettre cookie, bearer ou UUID privé.

## Gestes navigateur à effectuer par l'opérateur

Après l'ouverture limitée des flags par l'opérateur autorisé et confirmation que l'API et Office chargent effectivement ces valeurs :

1. Ouvrir `office.devsys.fr` avec la session habituelle, puis Factures → Nouveau brouillon.
2. Choisir le client fictif existant autorisé ; saisir une seule ligne et une référence métier unique `DEV-TEST-…` ; enregistrer une seule fois.
3. Confirmer un ID technique, `status=draft`, `invoice_number=null`, provenance `devsys` et référence métier distincte du numéro légal.
4. Rouvrir le même brouillon, modifier le libellé de la ligne, enregistrer puis recharger. Confirmer l'ID et la référence inchangés, avec la modification persistée.
5. Après l'essai, fermer les gates côté API d'abord (`INVOICE_WRITES_ENABLED=false`, allowlist vide), puis Office (`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`), puis le garde et ses attentes privées. Vérifier les états effectifs. Une fois Office OFF, ne pas créer de nouveau brouillon : le chemin historique SQL peut être repris.

Ne jamais cliquer Émettre/Valider, supprimer, créer un client ou soumettre une facture historique. Le brouillon fictif est conservé non émis pour MVP-2 ou nettoyage autorisé ultérieur.

## Résultat et limites

Création/PATCH : `NOT_RUN`. Aucun ID de brouillon ni référence de test ne sont à rapporter. Aucun changement de code, de configuration ou de release n'a eu lieu ; aucun nouveau test automatisé n'a été exécuté. Émission, numérotation, suppression, SQL métier et migration 024 : non effectués.

Résultat actuel : `SERVER_READY_FOR_OPERATOR`. Après les gestes, l'opérateur peut communiquer uniquement le résultat fonctionnel (création PASS/FAIL, modification persistée PASS/FAIL, flags remis OFF) ; les rapports seront alors complétés sans demander de secret ni de donnée personnelle.
