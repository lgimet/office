# OFFICE-INVOICE-NOTES-1 — intégration coordonnée

Date : 25 novembre 2026. Verdict : **`INTEGRATED_READY_FOR_DEPLOYMENT_PREPARATION`**. Les trois PR demandées sont fusionnées dans l’ordre shared → API → Office. Aucune opération de production n’a été faite.

## Intégration Git

| Dépôt | PR | Commit de code | Commit de merge `main` | HEAD `main` vérifié |
|---|---:|---|---|---|
| `lgimet/shared` | [#14](https://github.com/lgimet/shared/pull/14) | `3da1cabb276e6b0e39a2de2710777cba370b3a49` | `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf` | `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf` |
| `lgimet/Office-Api` | [#29](https://github.com/lgimet/Office-Api/pull/29) | `1b977efc3ca0b34733370fdcca0f02fa1baf5d18` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` |
| `lgimet/office` | [#23](https://github.com/lgimet/office/pull/23) | `3986a8da0804d2960f36aab0cd0d9e9f71a325d1` | `7df0921245494c97297c198695fc373af2e762ae` | `7df0921245494c97297c198695fc373af2e762ae` |

`office/composer.lock` et le `vendor/devsys/shared` contrôlé référencent tous deux le commit immuable `3da1cabb276e6b0e39a2de2710777cba370b3a49`, inclus dans `shared/main`. Seul le paquet `devsys/shared` a été actualisé dans la branche Office ; aucune mise à jour générale n’a été faite. Les PR étaient ouvertes et `MERGEABLE` au contrôle ; GitHub ne rapportait aucun statut CI ni décision de revue bloquante.

## Contrat intégré

Les paramètres tenantés PMD, PMT et AAB résident dans `company_settings` côté API et sont accessibles par Office via `CompanyInvoiceNotesService` → `shared/InvoicesApi` → API. L’identité tenant est toujours résolue côté serveur. La migration API `029_add_invoice_coded_notes.sql` ajoute trois colonnes de configuration nullable et trois colonnes snapshot nullable à `invoices` ; SHA-256 : `41eed5b3226a75a218caaeb0dea7c742fcc6fcd0e7c7887ef1ed5002959e8d9f`. Elle est ordonnée après 027 et 028 et ne contient aucun backfill.

La migration a été appliquée uniquement lors des tests sur `office_api_inv3_test`. Elle **n’a pas été appliquée en production dans ce lot** ; l’état de production n’a pas été consulté ni modifié. À l’émission, le candidat Factur-X est validé avant attribution du numéro, puis les notes sont figées dans le snapshot. Les factures classiques sans profil restent compatibles. Le PDF classique et le CII utilisent les notes du snapshot ; les anciens documents, factures et archives ne sont pas modifiés. Aucun texte légal n’est prérempli. Les validations techniques ne constituent pas une certification réglementaire française.

## Vérifications exécutées après intégration

- `shared`: `composer test` — PASS, 59 tests / 248 assertions ; `git diff --check` — PASS. PHPUnit a affiché un avertissement de permission lors de l’écriture du cache de résultats, sans échec de tests.
- `Office-Api`: `composer test` avec DSN explicite vers MariaDB locale jetable `office_api_inv3_test`, socket privé et `INVOICE_MYSQL_TEST_CONFIRM=isolated` — PASS, 346 tests / 1 501 assertions, zéro ignoré, 6 dépréciations PHPUnit. `SELECT DATABASE(), @@skip_networking` a retourné `office_api_inv3_test`, `1`. Les tests ont exercé la migration 029, les scopes/tenants, les notes manquantes avant numérotation, les snapshots et les rendus PDF/CII sur données synthétiques. `git diff --check` et lint PHP ciblé — PASS. L’instance SQL jetable a été arrêtée après les tests.
- `office`: `composer test` — PASS, 177 tests / 552 assertions ; `npm run build` — PASS ; `git diff --check` et lint PHP ciblé — PASS. Lock et vendor concordants sur le SHA shared ci-dessus.
- Vérification de l’ordre des migrations par fichiers : 027, 028, puis 029. SHA-256 recalculé de 029 conforme au rapport de développement.
- Les trois worktrees étaient propres après tests et les trois PR sont en état `MERGED`.

## Limites et suite

Aucun navigateur authentifié de production n’a été utilisé. Aucun endpoint métier de production n’a été appelé. Aucune migration de production, configuration, release, facture, numéro, snapshot ou archive n’a été touché. Les mentions doivent être rédigées et validées par le tenant avant une future émission au profil Factur-X ; les snapshots historiques restent tels quels.

**Prochaine étape unique :** préparer séparément l’application contrôlée de la migration 029 et les releases API puis Office, avec sauvegarde et retour arrière selon le runbook d’exploitation.
