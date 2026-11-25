# OFFICE-INVOICE-PDF-API-1 — intégration

Date : 25 novembre 2026  
Statut : **`INTEGRATED_READY_FOR_ACTIVATION` — non déployé**

## Intégration Git

Les trois PR ont été fusionnées dans l’ordre demandé, avec des commits de merge GitHub :

| Dépôt | PR | Commit de code de la branche | Commit de merge | `main` vérifiée après fusion |
|---|---:|---|---|---|
| `lgimet/shared` | [#13](https://github.com/lgimet/shared/pull/13) | `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d` | `93a0a8577094eb6f911cc54508eae14602399e15` | `93a0a8577094eb6f911cc54508eae14602399e15` |
| `lgimet/Office-Api` | [#28](https://github.com/lgimet/Office-Api/pull/28) | `ea2158b689a3ea253354fc55c1aa861a635819e0` | `b7bcf3264d4f7a392d2ed87593a13da088203e16` | `b7bcf3264d4f7a392d2ed87593a13da088203e16` |
| `lgimet/office` | [#21](https://github.com/lgimet/office/pull/21) | `eaee11e235ea5fd74e1dc31c2d180dcfc83935dc` | `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc` | `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc` |

Office `composer.lock` référence `devsys/shared` au commit exact `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d`. Le commit est inclus dans `shared/main`, et le `vendor/devsys/shared` vérifié dans le checkout Office pointe sur ce même SHA. Aucun dépôt n'avait de changement source local avant l'intégration.

## Contrat livré

- API : `POST /api/v1/invoices/{invoiceId}/pdf` déclenche explicitement le rendu et l'archivage ; `GET .../pdf/status` consulte l'état ; `GET .../pdf` restitue uniquement l'archive existante.
- Le rendu PDF ordinaire mPDF part des snapshots figés d'une facture API émise. L'API applique le tenant et les scopes serveur. L'archive est distincte de Factur-X, privée, idempotente, protégée contre l'écrasement et vérifiée par SHA-256.
- Office suit `DevsysInvoiceReadService → shared/InvoicesApi → API`. Le POST Office est authentifié et protégé contre CSRF ; son GET ne génère rien. Le PDF historique et le parcours Factur-X restent séparés.
- Ce PDF n'est pas annoncé comme PDF/A, Factur-X ou preuve de conformité EN 16931.

## Vérifications exécutées

| Dépôt / contrôle | Résultat |
|---|---|
| shared — `composer test` | PASS — 57 tests, 239 assertions |
| shared — `git diff --check` | PASS |
| Office-Api — `composer test`, DSN socket confirmé `office_api_inv3_test` | PASS — 344 tests, 1 472 assertions, 0 ignoré ; 6 dépréciations PHPUnit |
| Office-Api — `git diff --check` | PASS |
| office — `composer test` | PASS — 176 tests, 551 assertions |
| office — `npm run build` | PASS |
| office — `git diff --check`, lint PHP touché, `composer check-platform-reqs --no-dev` | PASS |
| office — SHA dans `composer.lock` et `vendor` | PASS — `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d` |
| office — `composer validate` | FAIL hors périmètre fonctionnel : métadonnée `description` absente pour publication |
| office — `composer audit --locked --no-dev` | FAIL sur 9 avis préexistants de Twig ; Twig n'a pas été modifié |
| Recette navigateur / production | NOT_RUN — aucune release, migration ou requête métier de production effectuée |

Les tests API incluent un rendu mPDF et un archivage avec des données synthétiques dans la base jetable ; snapshots, idempotence, concurrence, tenant et refus sur corruption sont couverts. Aucun document de production n'a été généré ou téléchargé.

## Préparation de mise en service — proposée, non exécutée

Les migrations `027_add_invoice_document_profile.sql` et `028_add_invoice_pdf_archives.sql` sont présentes dans le code. Les derniers rapports consultés indiquaient qu'elles n'étaient pas appliquées en production. Cet état n'a pas été interrogé en base pendant ce lot et doit être relu avant une mise en service. Empreintes dans le code fusionné :

- 027 : `db17f98228401a3ad5bf27ce76e59b2c5a5dc0169442eddaf5c76ac1e05e7be0` — colonne `document_profile` nullable, sans mise à jour rétroactive.
- 028 : `24d24bf602eed2de146eaf2da7fad97ce5afc3d8a9a84743cc1ae27d32efcd95` — index d'archives PDF, unicité par facture, clés tenant/facture/émetteur.

Étapes proposées pour un lot ultérieur explicitement autorisé :

1. Confirmer les commits servis, l'espace disponible et l'existence d'une sauvegarde récente via l'exploitation habituelle ; ne pas générer de document réel pendant le contrôle.
2. Vérifier en lecture seule registre et schéma. Si les deux migrations sont absentes, exécuter 027 puis 028 via le runner habituel seulement après avoir confirmé qu'il n'y a pas d'autre migration en attente. Si une table/colonne existe sans trace cohérente, arrêter et faire examiner l'état par le DBA ; ne pas rejouer ni supprimer.
3. Préparer `INVOICE_PDF_ARCHIVE_DIR` et `INVOICE_PDF_WORK_DIR` en chemins absolus privés, persistants, hors webroot et hors releases, avec accès minimal du worker PHP et sans suivi de liens symboliques. Ces variables sont déclarées dans `.env.example`, mais leurs valeurs de production n'ont pas été configurées ici.
4. Installer l'API complète en premier, puis Office avec le lock existant et le SDK épinglé. Conserver les configurations persistantes et les anciennes applications pour un retour arrière code. Ne pas remplacer directement les arbres servis par une copie partielle ; vérifier le mécanisme d'installation retenu et `apachectl configtest` avant tout éventuel reload.
5. Vérifier les contrôles santé et les routes protégées sans authentification, puis réaliser une recette documentaire uniquement avec une facture synthétique isolée ou une facture de test expressément autorisée. Un GET ne doit jamais déclencher la génération.
6. En cas de retour arrière applicatif, conserver les migrations, tables, archives et fichiers PDF : aucun `DROP` ni effacement d'archive. Arrêter si la compatibilité de l'ancienne version avec le schéma n'est pas établie.

Le lot n'a changé aucune release, configuration, flag, vhost, base, facture ni séquence de production. Les anciennes factures `2026-0001`, `2026-0002` et `2026-0003` n'ont pas été consultées ni modifiées. Les résultats de sauvegarde, la session navigateur et l'état courant de la base de production ne sont pas reconfirmés ici.

**Prochaine action unique :** planifier séparément une mise en service contrôlée API puis Office, après confirmation opérateur du statut réel des migrations et des deux chemins privés.
