# OFFICE-UI-INV-2.1 — raccordement sécurisé des brouillons

## Résultat

Le parcours Office sait maintenant créer et modifier des brouillons avec `shared/InvoicesApi`, tout en vérifiant la provenance API avant chaque mutation d’une facture existante. Les chemins API de création et d’édition sont implémentés, mais le flag Office reste **désactivé par défaut**. L’émission et la suppression d’une facture API restent interdites.

## Base de travail et contrats observés

- Dépôt Office : branche `feature/office-ui-inv-1.1`, HEAD de base `e599a4cb82b2a86a46cf5a78caa38c0c00bfc215`. `origin/main` local pointe sur `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`, en retard sur les travaux UI-INV-1/1.1/2 déjà présents dans la branche de travail. Les préserver était nécessaire pour implémenter ce lot. Aucun merge/rebase vers `main` n’a été fait.
- `Office-Api` : branche `feature/office-api-inv-2.1`, HEAD et `origin/main` à `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf`. Le GET détail fournit le client UUID, la provenance et l’ETag/révision; le PATCH contrôle de nouveau l’éligibilité côté serveur.
- `shared` : branche `feature/office-shared-inv-2-1`, HEAD `0b535cb28de2393d38c25d766967514b96d22b3e`; `origin/main` local est `a3abc4178fc8de90c350b5a3163f9fe1bde15b75` et ne contient pas encore le contrat `InvoiceOrigin`/DTO requis. Dans cet environnement `vendor/devsys/shared` pointe sur le checkout local de cette branche. Un `composer.lock` local ignoré a été mis à jour pour ce checkout, mais il n’est pas un livrable suivi et ne démontre pas le contenu installé en production.
- La tentative de rafraîchir les références distantes a échoué car `.git/FETCH_HEAD` est en lecture seule. Les SHA ci-dessus décrivent donc les références locales observées, pas une vérification distante en temps réel.

Le code vérifie à l’exécution la présence des propriétés de contrat attendues; si le SDK installé est trop ancien ou si la provenance manque, il ferme le parcours sans mutation. Avant recette, intégrer `OFFICE-SHARED-INV-2.1` dans la branche partagée utilisée par Composer, mettre à jour et vérifier le lockfile réellement déployé, puis valider la version sous `vendor/`.

## Changements Office

- `DevsysInvoiceDraftService` orchestre la création, l’édition et les décisions selon la provenance. `InvoiceDraftApiInterface` fournit une frontière testable et `SharedInvoiceDraftApi` délègue au SDK, sans HTTP direct depuis Office.
- Le contrôleur conserve les routes existantes. Il délègue les mutations au service, renvoie des erreurs sûres pour les réponses API et répète les contrôles côté serveur même lorsque les boutons sont cachés.
- Les formulaires API sont remplis depuis `InvoiceDetails` (client UUID/snapshot, lignes, montants et métadonnées). Ils sont en lecture seule si le flag est désactivé ou si l’API ne déclare pas le brouillon modifiable.
- Twig et JavaScript signalent le mode API, désactivent émission/suppression, bloquent les doubles sauvegardes et rechargent le GET après une sauvegarde confirmée. Les totaux API affichés après confirmation viennent des montants retournés par l’API. Les unités et modes de paiement personnalisés sont conservés dans leurs sélecteurs.
- `internal_note` reste inchangée pour les factures historiques. Dans le formulaire API, elle est présentée comme indisponible; toute valeur non vide reçue par le serveur bloque la sauvegarde. Elle n’est ni transmise à l’API ni copiée en SQL.

## Politique de provenance et mutations

Chaque action mutante avec ID appelle `InvoicesApi::get(id)` dans le contexte OIDC courant, indépendamment du flag d’écriture. Une origine absente, un ID incohérent, une erreur API/réseau ou une source autre que `historical` et `api/devsys` ferme le parcours. Aucun échec API ne retombe vers SQL.

| État vérifié côté API | Enregistrer | Émettre | Supprimer |
|---|---|---|---|
| Brouillon `historical` | Service SQL historique | SQL historique | SQL historique |
| Brouillon `api/devsys` éligible, flag activé | `updateDraft`, avec l’ETag de l’ouverture | Bloqué | Bloqué |
| Brouillon `api/devsys`, flag désactivé ou non éligible | Bloqué | Bloqué | Bloqué |
| Autre source, origine inconnue, ressource absente/inaccessible | Bloqué | Bloqué | Bloqué |
| Facture non brouillon ou numérotée | Bloqué | Bloqué | Bloqué |

La création sans ID utilise SQL historique quand le flag est faux; quand il est vrai, elle passe exclusivement par `createDraft`. Aucun sélecteur de tenant navigateur n’est utilisé comme autorité; les champs `tenant_id` et `tenant_uuid` sont refusés. `source_app=devsys` est fixé côté serveur.

## Idempotence, version et reprise

`InvoiceDraftWriteContextStore` conserve en session PHP les métadonnées de formulaire, liées au hash de la session, au tenant/utilisateur OIDC, au type et à l’ID. La session est bornée à 100 contextes et 24 heures; le verrou normal de session PHP sérialise les POST concurrents d’une même session. Les factures ne sont pas persistées dans cette session comme source métier.

Avant le premier POST de création, l’événement, la clé d’idempotence et le payload canonique sont enregistrés. Une reprise réutilise le même triplet; si les valeurs ont changé, elle est refusée. L’ID reçu est ensuite mémorisé, et une reprise avec cet ID relit la facture plutôt que d’émettre un nouveau POST. Après création ou rejeu, l’éditeur est rechargé depuis le GET API, qui fournit le nouveau contexte d’ETag/révision. Pour l’édition, l’ETag/révision est lié au contexte serveur créé à l’ouverture; les GET de précontrôle au clic ne remplacent pas cet ETag. La clé de mise à jour est stable pour le contexte, la version et le hash du payload. Un résultat rejoué provoque une lecture API de confirmation, puis le contexte est invalidé et l’éditeur rechargé. Un 412 garde les valeurs dans le formulaire et demande explicitement le rechargement.

## Mapping

Les DTO du SDK sont construits avec les quantités, prix, taux et remises sous forme de chaînes décimales; l’ordre des lignes est conservé. Les champs optionnels vides sont normalisés à `null`, une remise absente devient trois valeurs nulles, et aucun total, numéro, tenant, snapshot émetteur ou champ interdit n’est envoyé. Les montants sauvegardés sont ceux calculés par l’API; le calcul JavaScript reste un aperçu indicatif.

## Habilitations et recette

`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false` par défaut. Ne pas activer `INVOICE_WRITES_ENABLED` en production. Pour une recette autorisée uniquement : vérifier le SDK déployé à la version requise, configurer l’allowlist OAuth de `office-web` pour `write:invoices`, ajouter ce scope uniquement à la configuration de recette et ouvrir une nouvelle session OIDC. Activer ensuite le flag Office et les flags API d’écriture uniquement sur l’environnement isolé de recette. Vérifier création, retry après perte de réponse, édition concurrente/412, provenance historique, source étrangère, et POST forgés d’émission/suppression.

Rollback de recette : désactiver le flag Office. Les brouillons API restent identifiés par leur provenance et ne basculent jamais vers une écriture SQL. Les créations SQL historiques restent possibles lorsque le flag est désactivé. Ne pas exécuter de mutation sur des factures de production.

## Vérifications exécutées

- `composer test` — OK, 134 tests et 344 assertions.
- `npm run build` — OK.
- `git diff --check` — OK.
- `find src config tests -name '*.php' -print0 | xargs -0 -n1 php -l` — OK pour les fichiers PHP parcourus.
- `node --check public/assets/js/Core/Core.js` et `node --check public/assets/js/Object/Invoices.js` — OK.
- `composer validate --no-check-publish` — manifeste valide; avertissement préexistant d’absence de licence Composer.

Les tests API sont des tests locaux avec mocks. Aucune recette API/MySQL sur tenant isolé n’a été exécutée. Les vues émises/PDF et les chemins de lecture restent ceux existants; ce lot ne raccorde pas les mutations d’émission ou de suppression API.
