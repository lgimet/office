# OFFICE-INV-RECETTE-1 — rapport de recette

**Conclusion : BLOCKED — recette E2E non autorisée dans l’environnement présent.** Aucun POST/PATCH, test de navigateur, changement de flag/scope, accès à la base, ni migration n’a été exécuté. Les preuves locales ci-dessous ne valent pas preuve d’intégration E2E.

Date de contrôle : 21 novembre 2026. Hôtes, identifiants et données personnelles ne sont pas consignés.

## Motif d’arrêt

La garde d’isolation obligatoire échoue avant la première requête mutatrice : le fichier `.env` Office indique `DEVSYS_API_BASE_URL=https://api.devsys.fr` et les URLs OAuth sur `api.devsys.fr`; le `.env` API indique également `APP_URL=https://api.devsys.fr`. L’API est donc configurée vers l’hôte de production. Office indique `APP_ENV=dev`, `DB_HOST=localhost`, `DB_NAME=office`, tandis que l’API indique `APP_ENV=development`, `DB_HOST=localhost`; ces valeurs ne prouvent ni une base dédiée de recette ni un tenant fictif. Aucune URL Office/API distincte de recette, preuve d’isolation de base, sauvegarde de recette, tenant fictif identifié, ni accord opérateur pour l’activation et les mutations n’a été fourni.

Les flags d’écriture ne sont pas activés : dans `.env` Office, `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`; l’environnement du processus ne fournit pas ces flags. Les variables de test MariaDB `INVOICE_MYSQL_TEST_DSN` et `INVOICE_MYSQL_TEST_CONFIRM` sont absentes. Côté API, `HttpProvider` donne `false` par défaut à `INVOICE_WRITES_ENABLED` et une liste vide à `INVOICE_WRITE_SOURCES`; l’état d’un environnement distant n’a pas été interrogé.

En conséquence, `migrate:status` n’a pas été lancé : la configuration connue ne permet pas de garantir qu’une commande CLI ne se connecterait qu’à une base isolée. Les migrations requises existent dans le checkout API, mais leur application dans une base de recette n’est pas vérifiée. Aucun changement de scope OAuth/allowlist, aucune session OIDC de recette et aucun test réseau n’ont été réalisés.

## Versions et état des dépôts

Les références sont celles présentes localement; elles n’ont pas pu être actualisées dans ce contrôle. Un fetch antérieur a échoué car `.git/FETCH_HEAD` est en lecture seule. Aucun dépôt n’a été réinitialisé, fusionné, rebasé ou déployé.

| Dépôt | Branche / HEAD | `origin/main` local | État relatif |
|---|---|---|---|
| Office | `feature/office-ui-inv-1.1` — `f3975393f43f637b814112300e28263f4ba2c3de` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` | 4 commits feature en avance; le code UI-INV-2.1 est absent de main |
| shared | `feature/office-shared-inv-2-1` — `0b535cb28de2393d38c25d766967514b96d22b3e` | `a3abc4178fc8de90c350b5a3163f9fe1bde15b75` | 1 commit feature en avance d’après les références locales; `main` local est `2a15ecfa6794e090ec6ea941a2ecc62b3e139c12` |
| Office-Api | `feature/office-api-inv-2.1` — `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` | même SHA | HEAD égal à `origin/main`; `main` local est plus ancien (`ae64c533c7e72a92e30523b732cf17e65b83e6cc`) |

Les trois checkouts étaient propres avant les vérifications; aucun changement fonctionnel n’a été fait dans ces dépôts. Le rapport de recette est le seul nouveau livrable Office.

Office `composer.json` résout `devsys/shared` via un dépôt `path` vers `../shared`. `vendor/devsys/shared` est un symlink vers `/home/laurent/Sites/devsys/shared`; Composer affiche `dev-main`, et les classes `InvoiceOrigin`, `InvoiceDetails`, `InvoiceDraftUpdateResult`, ainsi que les méthodes `get`, `createDraft` et `updateDraft` sont chargées localement. Ce symlink et le `composer.lock` ignoré ne prouvent pas le SDK présent dans un déploiement. Il faut vérifier le mode Composer réellement utilisé par l’environnement de recette avant de le sélectionner.

Dans Office-Api, les migrations `022_add_invoice_source_idempotency.sql` et `023_add_invoice_draft_revisions_and_update_idempotency.sql` sont présentes. L’inspection statique montre les contrôles de source/tenant, le scope `write:invoices`, le contrôle de version et les tables d’idempotence dans le code. La présence des fichiers et du code n’établit pas que les migrations sont appliquées ni que les scopes/allowlists distants sont configurés.

## Tests locaux et preuves

| Dépôt | Commandes | Résultat |
|---|---|---|
| Office | `composer test` | PASS — 134 tests, 345 assertions |
| Office | `npm run build` | PASS |
| Office | `git diff --check`, lint PHP sous `src config tests`, `node --check` sur Core.js et Invoices.js | PASS |
| shared | `composer test` | PASS — 46 tests, 197 assertions; avertissement PHPUnit car le cache de résultats est en lecture seule |
| shared | `git diff --check`, lint PHP sous `src tests` | PASS |
| Office-Api | `composer test` | 226 tests, 779 assertions; 6 dépréciations PHPUnit, 11 tests sautés |
| Office-Api | `git diff --check`, lint PHP sous `src tests bin` | PASS |

Les 11 tests sautés sont les tests MySQL de `tests/Integration/InvoiceCommandMySqlIntegrationTest.php`; le DSN et la variable de confirmation `isolated` ne sont pas configurés. Aucun résultat MariaDB n’est déclaré PASS. L’API et shared n’ont pas de `package.json`; aucun build npm n’y est applicable. Les avertissements d’écriture du cache PHPUnit sur les checkouts read-only n’ont pas affecté les suites.

### Couverture mock pertinente

Les tests Office vérifient localement les séparations de parcours et les reprises, sans appels réseau ni écritures DB : `DevsysInvoiceDraftServiceTest` couvre provenance historique/API, flag OFF, création avec clé/événement/payload stables, timeout/réessai, ETag d’ouverture, conflit de version, note interne et source étrangère. `InvoicesDataSwitchTest` couvre notamment des POST forgés save/issue/delete et le GET API en erreur sans fallback SQL. Les tests SDK couvrent mapping/réponses simulées. Ces résultats ne vérifient ni OAuth réel, ni contrôleur/API HTTP intégré, ni persistance, ni verrouillage InnoDB sur la DB visée.

## Matrice R01–R22

`BLOCKED (E2E)` signifie que le scénario n’a pas été exécuté contre Office, Office-Api et une base/tenant isolés. Les indications entre parenthèses sont uniquement des preuves mock ou du code inspecté; elles ne changent pas le statut E2E.

| ID | Résultat | Preuve / prérequis restant |
|---|---|---|
| R01 | BLOCKED (E2E) | Liste et facture historique non ouvertes dans une recette isolée; mock de la provenance historique dans les tests Office. |
| R02 | BLOCKED (E2E) | Pas de POST API; le test mock vérifie source, clé/événement stables et champs envoyés. |
| R03 | BLOCKED (E2E) | GET réel, client/snapshot et ETag non vérifiés; mapping testé avec réponses mock. |
| R04 | BLOCKED (E2E) | PATCH réel non exécuté; ETag d’ouverture testé avec mocks. |
| R05 | BLOCKED (E2E) | Pas de persistance réelle des valeurs nullables/remises; mapping testé localement. |
| R06 | BLOCKED (E2E) | Pas de double clic dans l’UI; idempotence/reprise couverte par tests unitaires mock. |
| R07 | BLOCKED (E2E) | Aucune réponse réseau réelle perdue; retry de création vérifié avec mock. |
| R08 | BLOCKED (E2E) | Aucun PATCH réel perdu/rejoué; reprise ETag/clé vérifiée avec mock. |
| R09 | BLOCKED (E2E) | Aucun conflit de clé soumis à l’API; code 409 et tentative stable couverts partiellement en mock. |
| R10 | BLOCKED (E2E) | Deux éditeurs/sessions et 412 réel non testés; garde de précondition testée en mock. |
| R11 | BLOCKED (E2E) | Aucune facture de source `spotcard`/`stationid` réelle; code/tests bloquent une source API différente de devsys. |
| R12 | BLOCKED (E2E) | Flag OFF observé localement; les POST forgés API sont testés avec mock, pas contre recette. |
| R13 | BLOCKED (E2E) | Boutons/POST forgés non exercés dans un navigateur/serveur recette; tests de contrôleur mock vérifient l’absence d’appel SQL. |
| R14 | BLOCKED (E2E) | Aucune fixture historique en DB isolée; tests unitaires vérifient le choix SQL après GET provenance historique mocké. |
| R15 | BLOCKED (E2E) | Pas de POST connecté réel; refus de `internal_note` API et SQL historique couverts en tests. |
| R16 | BLOCKED (E2E) | UUID/client désactivé/origine absente non testés sur API réelle; origine absente simulée et fermeture contrôlée en test. |
| R17 | BLOCKED (E2E) | Pas de test inter-tenant; aucune donnée tenant n’a été consultée. |
| R18 | BLOCKED (E2E) | Pas de jeton OAuth/session de recette ni test scope; réponses 401/403 ne sont que simulées. |
| R19 | BLOCKED (E2E) | Statuts/réseau non provoqués sur une API réelle; messages et erreurs API vérifiés par mocks. |
| R20 | BLOCKED (E2E) | Aucun brouillon API réellement créé puis flag basculé; blocage après rollback couvert par test unitaire. |
| R21 | BLOCKED (E2E) | Aucune facture émise de fixture API; aucune émission n’a été tentée. |
| R22 | BLOCKED (E2E) | Concurrence MySQL non exécutée; le test d’intégration correspondant est sauté faute DSN/confirmation. |

## Écarts, remédiation et état final

1. Fournir un hôte Office de recette et un hôte API de recette distincts de `office.devsys.fr`/`api.devsys.fr`, avec preuve de leur routage vers une base dédiée, un tenant fictif et une stratégie de restauration. La configuration Office actuelle pointe vers l’API de production : ne pas l’utiliser pour la recette.
2. Vérifier la version shared réellement déployée depuis un artefact Composer reproductible. La résolution locale par symlink n’est pas suffisante.
3. Vérifier les migrations 022/023 sur une base de recette après accord explicite; avant cela, leur état appliqué reste inconnu.
4. Configurer `write:invoices` dans l’allowlist `office-web` de recette seulement et obtenir une nouvelle session OIDC. Ensuite seulement, obtenir l’accord opérateur explicite couvrant tenant fictif, hôtes, base, flags, données et fenêtre de recette.
5. Sous cette autorisation, activer les flags uniquement en recette (`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=true`, `INVOICE_WRITES_ENABLED=true`, `INVOICE_WRITE_SOURCES=devsys`), exécuter R01–R22 sur les fixtures convenues, puis remettre les flags d’écriture OFF et vérifier cet état.

À la fin de ce contrôle, aucun flag ni scope n’a été modifié; aucune donnée/fixture n’a été créée ou nettoyée. Pour Office, le flag d’écriture reste `false`. Côté API, aucune variable d’écriture n’est présente dans le fichier local `.env` et les défauts du code sont OFF/liste vide; l’état distant n’est pas connu.

**Décision : BLOCKED.** Aucun GO de recette n’est donné. Le GO éventuel ne pourra valoir que pour une recette explicitement isolée; il n’autorise jamais l’activation en production.
