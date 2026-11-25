# OFFICE-INVOICE-MVP-RECETTE

Date : 25 novembre 2026  
Verdict : **`MVP_NOT_READY`** pour un usage quotidien avec récupération garantie d’un document. La création/édition et l’émission sont présentes dans le parcours API, mais l’accès à un PDF pour les factures API n’est pas assuré.

## Références et environnements

| Dépôt | `origin/main` vérifié |
|---|---|
| `lgimet/office` | `8919628bb963acac5f02fde6d013c8e6908bc477` |
| `lgimet/Office-Api` | `e7461561d459b7b4bd381755a15537aee91c68f9` |
| `lgimet/shared` | `a3a17da2b840e7144376cb58eaca89407f3c39af` |

Branches de travail propres utilisées pour les inspections : Office `docs/office-invoice-mvp-recette` (issue de `main`), API `feature/office-api-release-active-gates`, shared `docs/office-invoice-facturx-2-issue-policy-deploy-report`. Aucun code applicatif n’a été modifié dans cette recette.

Production lue sans session utilisateur : API `api-invoice-facturx2-20261124-7f88c7c-r1`, Office `office-invoice-facturx2-20261124-eb2f7aa`; les `current` et DocumentRoots correspondent à ces releases. Les flags de facture et Factur-X sont ON, allowlist API `devsys`, et `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=true`. La migration 027 est présente dans `main`, mais absente de la release API active ; une lecture limitée de métadonnées MariaDB 10.9.4 confirme que `office.invoices.document_profile` n’existe pas. Aucun contenu de facture n’a été lu.

Office `main` verrouille `devsys/shared` sur le commit immuable `f1215a8f0a32c660115e64de75504018c930053e`. Les tests locaux ont utilisé les worktrees de recette dont les fichiers applicatifs correspondent à `origin/main`; aucun trafic métier de production n’a été envoyé.

## Matrice de recette

| Parcours | Résultat | Preuve / limite |
|---|---|---|
| A. Recherche et sélection du client | `NOT_RUN` | Les routes authentifiées `Invoices/client-options` et `clientDetails` délèguent à `shared/ClientsApi`; le navigateur et une session tenantée n’étaient pas disponibles. Le client est transmis comme destinataire (`client_uuid`), le tenant reste résolu côté API. |
| B. Brouillon, lignes, sauvegarde et modification | `OK` automatisé, navigateur `NOT_RUN` | Le parcours API est présent dans `DevsysInvoiceDraftService` et le formulaire. `composer test` Office : 174 tests / 542 assertions ; `npm run build` PASS. Aucun brouillon n’a été créé en production. |
| C. Émission | `NOT_RUN` en recette utilisateur | L’action d’émission Office existe. `main` API contient le contrôle pré-numérotation `INVOICE_FACTURX_PREISSUE_INCOMPLETE` pour les brouillons optés. La release API active ne contient pas 027 et la release Office ne présente pas le sélecteur `document_profile`; cette protection Factur-X n’est donc pas disponible en production. Le flux sans profil reste le flux historique. Aucun numéro n’a été consommé. |
| D. Consultation d’une facture API | `OK` côté code, navigateur `NOT_RUN` | Office lit les détails via le service/SDK et expose les snapshots de facture émise en lecture seule. Aucun accès à une facture réelle n’a été fait pendant la recette. |
| D. PDF ordinaire d’une facture API | `FAIL` | `Invoices::pdf()` renvoie HTTP 409 pour une facture d’origine API ; `DevsysInvoiceReadService::view()` fixe `pdf_enabled=false`. Le renderer mPDF historique utilise un modèle Office versionné et n’est pas appelé pour ce chemin. |
| D. Factur-X | `NOT_RUN` sur facture de test autorisée | L’action explicite génération/téléchargement existe pour les factures API émises et éligibles. Aucune facture de test déjà émise et explicitement autorisée n’était disponible ; aucun POST de génération n’a été envoyé. Le refus connu de `2026-0003` pour snapshot incomplet reste inchangé et n’a pas été rejoué. |
| E. Authentification, tenant et absence de SQL de secours | `OK` automatisé, session réelle `NOT_RUN` | Les routes Office sont `AuthRequired`; les flux API passent par `shared/InvoicesApi`, sans `tenant_id` navigateur faisant autorité. Les suites Office/shared/API sont passées (détail ci-dessous). Aucun tenant ni document d’un autre utilisateur n’a été consulté. |

## Défauts et priorité

- **Bloquant MVP — document quotidien :** une facture API émise peut être consultée mais ne dispose pas d’un PDF ordinaire téléchargeable dans Office. Le Factur-X est une voie distincte et seulement disponible pour des snapshots éligibles ; le snapshot incomplet documenté pour `2026-0003` montre que ce n’est pas un substitut universel.
- **Bloquant pour le profil Factur-X en production :** la politique d’émission de `main` n’est pas servie. 027 et le choix de profil ne sont pas présents dans les releases actives. Ne pas annoncer que les nouvelles émissions sont prévalidées pour Factur-X.
- **Gênant :** le bouton « Générer le Factur-X » est présenté pour les factures API émises avant que leur représentabilité soit connue ; le refus reste explicite et l’interface sait afficher les catégories sûres de données manquantes.
- **Plus tard :** parcours navigateur authentifié, document généré depuis une fixture autorisée dans un environnement isolé, et complétude réglementaire française exhaustive. Aucun résultat XSD/PDF-A n’est assimilé à une certification juridique.

## Contrôles exécutés

- Office `composer test` : PASS, 174 tests / 542 assertions.
- Office `npm run build` : PASS.
- shared `composer test` : PASS, 55 tests / 230 assertions.
- Office-Api `composer test` : PASS, 338 tests / 1 012 assertions ; 30 tests ignorés car aucun DSN MySQL isolé n’a été fourni, et 6 dépréciations PHPUnit. Les tests de persistance/pré-émission n’ont donc pas été exercés pendant cette recette.
- MariaDB production : lecture de métadonnées seulement, version 10.9.4 ; colonne `document_profile` absente. Aucun DDL ni lecture de lignes métier.
- Les suites ci-dessus ne constituent pas une recette navigateur ni une émission réelle.

## Prochaine action concrète

Traiter le blocage du PDF quotidien dans un lot ciblé : fournir pour les factures API un PDF ordinaire issu exclusivement des snapshots immuables, généré et archivé côté API puis servi dans Office via `shared/InvoicesApi`. Garder Factur-X séparé et ne pas présenter ce PDF comme conforme Factur-X. Avant toute activation du profil Factur-X en production, la migration 027 et la release Office correspondante devront ensuite être déployées par leur procédure autorisée.

État final : **`MVP_NOT_READY`**. Aucune émission, génération documentaire, modification d’archive, migration, bascule de release ou modification de flag n’a été effectuée.
