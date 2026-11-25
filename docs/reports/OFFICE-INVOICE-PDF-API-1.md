# OFFICE-INVOICE-PDF-API-1 — PDF ordinaire des factures API

Branche `feature/office-invoice-pdf-api-1`, base `main` `8919628bb963acac5f02fde6d013c8e6908bc477`, commit `a02ff6e`. PR Office [#21](https://github.com/lgimet/office/pull/21) ; dépend des PR API [#28](https://github.com/lgimet/Office-Api/pull/28) et shared [#13](https://github.com/lgimet/shared/pull/13). SDK verrouillé et installé : `devsys/shared` commit immuable `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d`.

## Fonctionnement

- Sur une facture API émise, l’action **Générer le PDF** envoie un POST Office authentifié et protégé CSRF. Le GET Office ne génère jamais ; il restitue seulement l’archive existante.
- Trajet : Office → `DevsysInvoiceReadService` → `shared/InvoicesApi` → API. Office n’ajoute ni SQL métier ni appel HTTP direct.
- L’API rend un PDF A4 mPDF à partir des snapshots immuables (émetteur, destinataire, lignes, montants et conditions présentes). Elle rejette brouillon, historique et données minimales manquantes, archive une seule version avec verrou, publication atomique et SHA-256.
- La migration additive `028_add_invoice_pdf_archives.sql` est requise avec `INVOICE_PDF_ARCHIVE_DIR` et `INVOICE_PDF_WORK_DIR` en chemins privés. Elle a été exécutée uniquement dans la base jetable; aucune migration de production, facture ou configuration serveur n’a été touchée.
- Le PDF est ordinaire. Ce lot n’ajoute aucune revendication PDF/A, XML Factur-X ou conformité EN 16931. Le PDF historique Office conserve son chemin mPDF existant ; le parcours Factur-X reste séparé.

## Vérifications

| Contrôle | Résultat |
|---|---|
| API `composer test`, MariaDB jetable `office_api_inv3_test` | PASS — 344 tests, 1 472 assertions, 0 ignoré ; six dépréciations PHPUnit |
| shared `composer test` | PASS — 57 tests, 239 assertions |
| Office `composer test` | PASS — 176 tests, 551 assertions |
| Office `npm run build` | PASS |
| `git diff --check`, lint PHP touché | PASS |
| SDK installé dans Office | PASS — HEAD `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d` |
| `composer check-platform-reqs --no-dev` | PASS |
| `composer validate` | FAIL préexistant au périmètre : `description` manquante pour publication, licence non spécifiée |
| `composer audit --locked --no-dev` | FAIL — neuf avis existants concernant Twig ; Twig n’a pas été mis à jour dans ce lot |
| Session navigateur, API et Office en production | NOT_RUN — aucun déploiement ni document réel autorisé dans ce lot |

Le test Office simule les réponses API et confirme la restitution binaire `%PDF-`. Le test API exécute mPDF et l’archivage réel sur des données synthétiques, y compris changements des fiches courantes après émission simulée, idempotence, concurrence à deux processus, isolation tenant et corruption fail-closed.

État : **IMPLEMENTED_NOT_DEPLOYED**. Après revue/fusion des PR API #28, shared #13 et Office, le prochain pas est un déploiement séparé comprenant la migration 028 et les deux répertoires privés. Aucune facture `2026-0001` à `2026-0003` n’a été lue ou modifiée.
