# OFFICE-INVOICE-MVP-1-ENABLE-TEST — Office

Date : 2026-11-23 (Europe/Paris)

## Résultat

`NEEDS_OPERATOR_IDENTITY_VERIFICATION`. Le site est prêt pour que l'opérateur termine les validations privées, mais l'API d'écriture n'est pas ouverte. Aucun formulaire n'a été soumis et aucune facture n'a été créée ou modifiée.

## Runtime constaté

- Office : `office-invoice-mvp1-20261123-8150e7f`, SHA `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- API/login : `api-invoice-mvp1-20261123-4f5baec`, SHA `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`.
- SDK `devsys/shared` verrouillé et installé : `b845450b2d4004fa31a5e3a540184fff42c7ebba`.
- `current` pointe sur les deux releases. Santé API 200, OIDC discovery 200, factures anonymes 401.
- Office `.env` mode `0640`, `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`; aucune surcharge correspondante observée dans PHP-FPM. API write gate et garde absents/OFF, sources vides, attentes privées absentes.

## Sécurité du chemin Office

Office utilise `DevsysInvoiceDraftService` puis `shared/InvoicesApi`. Le flag Office false sélectionne toutefois, pour une création neuve, `legacy->saveDraft()` (SQL historique). Pour cette raison, le formulaire ne doit pas être soumis tant que le flag API n'est pas effectivement vrai. Une facture reconnue comme API n'utilise pas le SQL historique en fallback lorsque l'appel API échoue.

La session opérateur n'est pas disponible à Codex. Le scope réel `write:invoices`, le tenant serveur courant et le client fictif existant n'ont donc pas pu être vérifiés. Aucun cookie, bearer, UUID de tenant ou autre secret n'a été lu ou demandé.

## Procédure navigateur, après validation et ouverture côté serveur

L'opérateur autorisé confirme en privé son principal utilisateur et le scope `write:invoices`, le tenant effectif côté serveur et l'accès au client fictif. Après mise en place et vérification des protections API (rapport API associé), il règle Office `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=true`, puis dans sa session habituelle :

1. Factures → Nouveau brouillon ; sélectionner le client fictif appartenant au tenant validé ; saisir une ligne fictive et une référence unique `DEV-TEST-…` ; enregistrer une seule fois.
2. Confirmer l'ID technique, le statut `draft`, l'absence de numéro légal et la provenance `devsys`.
3. Rouvrir ce même brouillon, modifier le libellé de ligne, sauvegarder puis recharger. Vérifier le même ID et la modification persistée.
4. Ne pas Émettre/Valider, supprimer, créer un client ou modifier une facture historique.
5. À la fin du test, fermeture API d'abord (`INVOICE_WRITES_ENABLED=false`, allowlist vide), Office ensuite (`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`), garde enfin et retrait des attentes privées. Vérifier le retour aux états initiaux. Ne plus créer après le flag Office OFF : la création pourrait emprunter le SQL historique. Conserver le brouillon non émis.

Ne transmettre à Codex que `PASS/FAIL` pour création, modification persistée et fermeture des flags ; un ID technique peut être conservé par l'opérateur pour le suivi. Ne communiquer aucune identité, donnée client, cookie ou jeton.

## Non exécuté

Validation authentifiée/scopes/client : `NOT_VERIFIED`. Flag Office : laissé à `false`. Création/PATCH : `NOT_RUN`. Aucune modification de configuration, aucun SQL, aucune migration, émission ou suppression. Aucun code modifié ni tests relancés.
