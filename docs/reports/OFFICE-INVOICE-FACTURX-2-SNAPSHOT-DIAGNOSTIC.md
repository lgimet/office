# OFFICE-INVOICE-FACTURX-2-SNAPSHOT-DIAGNOSTIC

Date : 24 novembre 2026

## Statut

**`PARTIAL`** — le 422 Factur-X est maintenant présenté avec des catégories de champs sûres lorsqu'elles sont fournies. Le contrôle complet avant émission n'est pas étendu : le périmètre Factur-X n'est pas sélectionnable par facture/source de manière assez précise pour préserver sans risque les émissions hors profil.

## Références et changements

- Bases rafraîchies : Office `c08b558b9249d8705842d465b83d93ee7cd605f2`, shared `d1fd8eda2e0c5eb5b5ef40dbe8dc9813330030b8`, Office-Api `7f88c7ca5de84313aae262c7483ed7f49d9be4f4`.
- Branche de travail Office : `feature/office-invoice-facturx-2-snapshot-diagnostic`.
- Branche de travail shared : `feature/office-invoice-facturx-2-snapshot-diagnostic`.
- Office : ajout d'un formateur borné pour `FACTURX_SNAPSHOT_INCOMPLETE`; la réponse utilisateur ne contient que des catégories génériques (client, émetteur, paiement, lignes, totaux, facture), jamais les codes bruts, valeurs, payloads ou IBAN. Liste absente, invalide, trop longue ou code inconnu : message générique sûr. Les autres HTTP et codes gardent leur traitement existant.
- Shared : aucun changement de contrat ; ajout d'un test établissant que le SDK conserve le code d'erreur et `details.missing_fields` de la réponse API dans `DevsysApiValidationException::getErrors()`.
- Office-Api : code inchangé. Le contrôleur Factur-X transmet déjà les `details` de `InvoiceCommandException`; `DevsysApiClient` les conserve dans l'exception de validation.

## Diagnostic et limites

- Le journal Office récent a enregistré HTTP 422, `FACTURX_SNAPSHOT_INCOMPLETE` pour la tentative de génération. Le modèle canonique échoue avant rendu et archivage. Les champs manquants exacts ne sont pas dans le journal consulté.
- Aucun accès à la session authentifiée opérateur n'était disponible ; la facture `2026-0003` n'a pas été relue via API/SDK dans ce lot. Aucun POST de génération, téléchargement, changement de facture ou d'archive n'a été effectué.
- Le flux `issueDraft()` résout tenant, client et émetteur sous transaction/verrou ; la politique préexistante vérifie déjà l'adresse minimale du client et les codes unité/TVA avant `nextInvoiceNumber()`. Cette politique est actuellement appliquée sans distinction de source.
- Le modèle Factur-X complet exige davantage (notamment parties françaises, EUR, identifiants, paiement, mentions codées et concordance des montants). L'API expose un flag Factur-X global, mais aucun marqueur fiable de facture/source « dans le périmètre Factur-X » ne permet d'appliquer ces exigences plus strictes sans bloquer aussi des émissions hors profil. La refonte de ce périmètre est donc laissée hors de ce correctif ; aucune règle supplémentaire d'émission n'est annoncée comme livrée.

## Vérifications

- Shared `composer test` : PASS, 54 tests / 224 assertions.
- Office `composer test` : PASS, 173 tests / 529 assertions. Le `composer install` frais a échoué car l'archive GitHub du SDK privé épinglé n'était pas téléchargeable ; les tests ont utilisé le `vendor` du worktree Factur-X 2 existant, avec lock identique et `devsys/shared` vérifié au commit `ab5ba633dcb3247bb7f6751f0801bd4fca97da77`.
- `git diff --check` : PASS dans les deux dépôts.
- `php -l` des fichiers PHP modifiés : PASS.
- `npm run build` : non exécuté, aucun JavaScript/CSS n'a changé.
- Aucun test de pré-émission (Lot B) n'est revendiqué, car aucun comportement correspondant n'a été ajouté.

Ni merge ni déploiement effectué. Les factures émises, snapshots, numéros et séquences restent inchangés.
