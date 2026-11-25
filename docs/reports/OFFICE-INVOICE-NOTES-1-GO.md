# OFFICE-INVOICE-NOTES-1-GO

Date : 25 novembre 2026 (CET)  
Verdict : **`WAITING_MIGRATION_GO`**. Les candidates sont construites et inactives. La migration 029 et les bascules n'ont pas été exécutées ; le point A attend le GO explicite de l'opérateur.

## Sources et état de production observé

| Dépôt | `origin/main` vérifié | Code notes inclus |
|---|---|---|
| `shared` | `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf` | `3da1cabb276e6b0e39a2de2710777cba370b3a49` |
| `Office-Api` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `1b977efc3ca0b34733370fdcca0f02fa1baf5d18` |
| `office` | `7df0921245494c97297c198695fc373af2e762ae` | `3986a8da0804d2960f36aab0cd0d9e9f71a325d1` |

PR shared #14, API #29 et Office #23 sont déjà fusionnées. Le lock Office et `vendor/devsys/shared` de la candidate pointent tous deux sur le code immuable `3da1cabb276e6b0e39a2de2710777cba370b3a49` ; SHA-256 de `composer.lock` : `219a21b813ba5da1e505b181107d019f4e287ea580cd850e505ad5be727e6d1a`.

Les liens de production sont restés inchangés : API `api-office-mvp-pdf-20261125-a115d5f`, Office `office-mvp-pdf-20261125-dfaa435`. Les vhosts actifs servent ces releases. Le fichier Office `sites-enabled` est une configuration réelle distincte de `sites-available` : le premier pointe aujourd'hui directement vers l'ancienne release, le second vers `current/public`. Toute future bascule devra donc sauvegarder et modifier uniquement le vhost actif Office, vérifier les chemins, puis utiliser le `current` atomique. Aucun vhost ni pointeur n'a été modifié ici.

Contrôles préalables observés : API health loopback 200, route issuer-notes sans session 401, Office `/` 302, découverte OIDC login 200 ; `apachectl configtest` et `php-fpm8.4 -t` réussis, sans reload. Les flags Factur-X, d'écriture API `devsys`, de lecture API Office et de brouillons API étaient actifs et n'ont pas changé. Les chemins d'archives PDF/Factur-X et runtime privés préexistants sont restés intacts.

## Migration 029 — état lecture seule

Base cible confirmée par `SELECT DATABASE()` : `office` (MariaDB 10.9.4). Les migrations 027 et 028 sont enregistrées et leur schéma présent. La migration 029 est absente du registre et les six colonnes sont absentes ; aucune application partielle n'a été observée. La comparaison à la liste source ne montre pas d'autre migration en attente.

SHA-256 du fichier vérifié dans la source et la candidate : `41eed5b3226a75a218caaeb0dea7c742fcc6fcd0e7c7887ef1ed5002959e8d9f`. Il contient deux `ALTER TABLE`, ajoutant trois colonnes `VARCHAR(2000) NULL` à `company_settings` et trois colonnes nullable à `invoices`, sans `UPDATE` historique. Les DDL MariaDB ne sont pas transactionnels en bloc.

Le runner `php bin/console migrate` parcourt toutes les migrations non enregistrées ; `migrate:status` crée lui-même `migrations`. Aucun argument de migration unique n'existe. Aucun de ces runners n'a été lancé. Après le GO migration, l'exécution devra rester strictement limitée au fichier 029, puis inscrire uniquement 029 dans le registre après contrôle des six colonnes. Si une seule opération DDL échoue ou si registre et schéma divergent : STOP, conserver les colonnes déjà créées, ne pas faire de `DROP` ni de relance aveugle, et faire examiner l'état par le DBA. Un rollback applicatif ne retire pas la migration.

Sauvegarde habituelle : dernier succès du service observé le 25 novembre à 03:26 CET ; le journal de restauration isolée associé indiquait une vérification réussie. Aucune sauvegarde ou restauration n'a été lancée pour ce lot. Cela ne constitue pas une garantie de restauration de la base active au moment du GO.

## Candidates construites, inactives

| Application | Release candidate | Source | SHA-256 `SHA256SUMS` | Taille constatée |
|---|---|---|---|---:|
| API | `api-invoice-notes1-20261125-3482534-r1` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `39eb43fec88088342d35dc6a5385f875f3db12edd0c207c8efcfe51f21bebda4` | ~100 MiB |
| Office | `office-invoice-notes1-20261125-7df0921-r1` | `7df0921245494c97297c198695fc373af2e762ae` | `91b82a64869842a3ecfa447ef2e0aeeebebc8e13403fdbb5b9cf73b08d90f337` | ~311 MiB |

Chemins sous `/home/devsys/{api-deploy,office-deploy}/releases/`. Les deux manifestes et listes de checksums ont été vérifiés ; les liens `.env`/`storage` de l'API ciblent les emplacements persistants attendus. La candidate API inclut les migrations 026–029 ; 029 a été vérifiée séparément car le helper ne la valide pas. L'empreinte de migration est celle ci-dessus. Office utilise les assets compilés et son `vendor` contient le SDK exact du lock.

Le dry-run du helper API-only avec `--profile active` a passé. Il propose uniquement les deux vhosts API et `api-deploy/current`, laisse les fichiers login intacts et rappelle que le reload gracieux Apache est global et relit aussi login. Le dry-run n'a rien modifié. Son identifiant de sauvegarde affiché est une proposition, pas une sauvegarde créée. Aucun dry-run de bascule Office n'a été exécuté.

Espace vérifié après préparation : `/home/devsys` sur `/dev/sdb`, environ 15 GiB libres, 50 % utilisés et 12 % d'inodes utilisés. Les candidates sont conservées hors DocumentRoot ; aucun cache, worktree, release active, archive ou sauvegarde n'a été supprimé.

## Tests et contrôles réalisés

| Contrôle | Résultat |
|---|---|
| shared `composer test` | PASS — 59 tests, 248 assertions ; avertissement de cache PHPUnit non inscriptible, sans échec |
| API `composer test` | PASS — 346 tests, 1 501 assertions sur MariaDB isolée `office_api_inv3_test`, socket privé et `skip-networking` ; 6 dépréciations PHPUnit |
| Office `composer test` | PASS — 177 tests, 552 assertions |
| Office `npm run build` | PASS |
| `git diff --check` dans les trois sources | PASS |
| `php -l` sur contrôleurs/services de notes API et Office | PASS |
| API candidate `composer validate`, `check-platform-reqs --no-dev`, `audit --locked --no-dev` | PASS validation (avertissement licence), PASS plateforme, aucun avis de sécurité |
| Office candidate `composer validate`, `check-platform-reqs --no-dev` | PASS (avertissements licence/version racine), PASS plateforme |
| Office `composer audit --locked --no-dev` | FAIL/avertissement préexistant : 9 avis sur `twig/twig` verrouillé ; aucune mise à jour hors périmètre |
| API helper `activate --profile active --dry-run` | PASS ; aucun changement d'état |

Le premier lancement de la suite API n'a pas utilisé les racines de test Office/shared intégrées et a échoué sur la méthode SDK absente. Relancé avec les worktrees notes corrects, le test HTTP intégré est passé dans les 346 tests/1 501 assertions indiqués. Le daemon MariaDB de test a été arrêté après la suite. Aucun test n'a utilisé la base de production.

## Point A et suite autorisée

Aucune écriture SQL, édition de configuration, bascule `current`, modification de vhost, reload, génération documentaire ou mutation de facture n'a eu lieu. Les factures émises, snapshots, archives, séquences, flags, OIDC/login et paramètres persistants n'ont pas été touchés.

Pour continuer, l'opérateur doit répondre exactement : **`GO MIGRATION 029`**. Ce GO autorisera uniquement la vérification finale de l'état cible puis l'application ciblée de 029 sur la base `office`, et le contrôle post-DDL. Il n'autorisera ni bascule API/Office ni reload Apache. Après migration cohérente, un second bilan présentera les releases et le diff exact du vhost actif avant de demander séparément **`GO BASCULE API OFFICE + RELOAD APACHE GLOBAL`**.

Les contrôles métier authentifiés des paramètres et toute émission restent hors de ce lot. Aucune note juridique n'a été préremplie ; aucune conformité réglementaire exhaustive n'est revendiquée.
