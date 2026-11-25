# OFFICE-INVOICE-NOTES-1 — Office

Branche `feature/office-invoice-notes-1`, basée sur `origin/main` `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc`. Le SDK utilisé par `composer.lock` et `vendor/devsys/shared` est épinglé au commit `3da1cabb276e6b0e39a2de2710777cba370b3a49` sur `feature/office-invoice-notes-1`.

## Changement

Les paramètres de société existants accueillent maintenant trois champs distincts PMD, PMT et AAB. Ils sont chargés et enregistrés via `CompanyInvoiceNotesService` → `shared/InvoicesApi` → API, sans ajouter de lecture/écriture SQL métier pour ces notes dans Office et sans envoyer d'identifiant de tenant. Les champs sont facultatifs, limités à 2 000 caractères, sans clause juridique préremplie. L'interface précise qu'ils seront figés sur les prochaines factures émises et que leur rédaction doit être vérifiée.

Les erreurs sûres de prévalidation Factur-X reconnaissent les codes manquants des trois notes. La lecture de facture expose les valeurs du snapshot pour le rendu documentaire. Rien ne permet de modifier une facture émise depuis ces champs.

## Vérifications

- `composer test` : PASS, 177 tests / 552 assertions.
- `npm run build` : PASS.
- `git diff --check` : PASS.
- `composer.lock` et le `vendor/devsys/shared` installé utilisent le même commit immuable `3da1cabb276e6b0e39a2de2710777cba370b3a49` ; mise à jour Composer ciblée du paquet `devsys/shared`, aucune mise à jour générale.
- Contrôle UI via tests automatisés ; aucune session navigateur de production ni action métier réelle utilisée.

## Compatibilité et limites

Les notes ne sont pas requises pour les émissions classiques sans profil Factur-X. Les brouillons avec le profil `facturx_en16931` sont refusés avant attribution du numéro si elles sont absentes. Les textes doivent être configurés et validés par l'émetteur ; l'interface ne prétend pas déterminer leur applicabilité juridique. Aucun PDF ou XML historique n'est réécrit. Aucun déploiement n'a été effectué.

Statut : `IMPLEMENTED_NOT_DEPLOYED`, sous réserve de la revue et de la fusion coordonnée des PR API, shared et Office.
