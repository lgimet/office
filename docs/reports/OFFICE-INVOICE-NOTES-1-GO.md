# OFFICE-INVOICE-NOTES-1-GO

Date : 25 novembre 2026 (CET)  
Verdict : **`WAITING_SWITCH_GO`**. Après le GO explicite, la migration 029 a été appliquée et vérifiée sur `office`. Les candidates API/Office restent inactives ; aucune bascule ni reload n'a eu lieu.

## Sources et état de production observé

| Dépôt | `origin/main` vérifié | Code notes inclus |
|---|---|---|
| `shared` | `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf` | `3da1cabb276e6b0e39a2de2710777cba370b3a49` |
| `Office-Api` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `1b977efc3ca0b34733370fdcca0f02fa1baf5d18` |
| `office` | `7df0921245494c97297c198695fc373af2e762ae` | `3986a8da0804d2960f36aab0cd0d9e9f71a325d1` |

PR shared #14, API #29 et Office #23 sont déjà fusionnées. Le lock Office et `vendor/devsys/shared` de la candidate pointent tous deux sur le code immuable `3da1cabb276e6b0e39a2de2710777cba370b3a49` ; SHA-256 de `composer.lock` : `219a21b813ba5da1e505b181107d019f4e287ea580cd850e505ad5be727e6d1a`.

Les liens de production sont restés inchangés : API `api-office-mvp-pdf-20261125-a115d5f`, Office `office-mvp-pdf-20261125-dfaa435`. Les vhosts actifs servent ces releases. Le fichier Office `sites-enabled` est une configuration réelle distincte de `sites-available` : le premier pointe aujourd'hui directement vers l'ancienne release, le second vers `current/public`. Toute future bascule devra donc sauvegarder et modifier uniquement le vhost actif Office, vérifier les chemins, puis utiliser le `current` atomique. Aucun vhost ni pointeur n'a été modifié ici.

Contrôles préalables observés : API health loopback 200, route issuer-notes sans session 401, Office `/` 302, découverte OIDC login 200 ; `apachectl configtest` et `php-fpm8.4 -t` réussis, sans reload. Les flags Factur-X, d'écriture API `devsys`, de lecture API Office et de brouillons API étaient actifs et n'ont pas changé. Les chemins d'archives PDF/Factur-X et runtime privés préexistants sont restés intacts.

## Migration 029 — appliquée après GO

Le point A a été autorisé par le message opérateur **`GO MIGRATION 029`**. Juste avant l'application, la connexion de production a confirmé `DATABASE()=office` (MariaDB 10.9.4), 027/028 enregistrées, les six colonnes absentes et, en comparant le registre à la liste de la candidate, une seule migration en attente : `029_add_invoice_coded_notes.sql`. SHA-256 vérifié dans la source et la candidate : `41eed5b3226a75a218caaeb0dea7c742fcc6fcd0e7c7887ef1ed5002959e8d9f`.

Le runner `php bin/console migrate` parcourt toutes les migrations non enregistrées ; `migrate:status` crée lui-même `migrations`. Aucun de ces runners n'a été lancé. L'application a exécuté les deux statements `ALTER TABLE` du fichier 029 exact via la connexion PDO de l'API, avec `lock_wait_timeout=5`, puis a inscrit uniquement `029_add_invoice_coded_notes.sql` dans le registre. Les deux statements ont réussi. Après application, les six colonnes ont été relues dans `information_schema` et confirmées `VARCHAR(2000) NULL`; le registre contient 029 une seule fois et la comparaison à la candidate ne trouve aucune migration en attente.

La migration ne contient que des `ALTER TABLE`, sans `UPDATE` historique ; aucune ligne de facture n'a été lue ni modifiée par cette opération. Les DDL MariaDB ne sont pas transactionnels en bloc. En cas de divergence ultérieure : STOP, conserver les colonnes, ne pas faire de `DROP` ni de relance aveugle, et faire examiner l'état par le DBA. Un rollback applicatif ne retire pas la migration. Le compte SQL ne disposait pas du privilège `PROCESS` ; l'attente de verrou DDL a été bornée à cinq secondes et les deux statements ont terminé avec succès.

Sauvegarde habituelle : dernier succès du service observé le 25 novembre à 03:26 CET ; le journal de restauration isolée associé indiquait une vérification réussie. Aucune sauvegarde ou restauration n'a été lancée pour ce lot. Cela ne constitue pas une nouvelle preuve de restauration de la base active au moment de la bascule.

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

## Point B — bascules encore en attente

Après l'application, la vérification en lecture seule confirme `DATABASE()=office`, 027/028/029 enregistrées, zéro migration candidate en attente et les six colonnes nullable de longueur 2 000. API health loopback 200, route `GET /api/v1/invoices/issuer-notes` sans session 401, Office `/` 302 et découverte OIDC login 200. Le dry-run API-only `--profile active` repasse ; il propose les deux vhosts API et le pointeur API uniquement. Aucun vhost, pointeur, flag, service ou release active n'a été modifié/rechargé.

État avant bascule : API `api-office-mvp-pdf-20261125-a115d5f` et Office `office-mvp-pdf-20261125-dfaa435`. Releases candidates : API `api-invoice-notes1-20261125-3482534-r1` (source `34825341bab7ed1a8cc16bc6bf7f04bef2427f73`, manifeste SHA-256 `39eb43fec88088342d35dc6a5385f875f3db12edd0c207c8efcfe51f21bebda4`) ; Office `office-invoice-notes1-20261125-7df0921-r1` (source `7df0921245494c97297c198695fc373af2e762ae`, manifeste SHA-256 `91b82a64869842a3ecfa447ef2e0aeeebebc8e13403fdbb5b9cf73b08d90f337`). Les candidates restent disponibles et leurs `current` ne pointent pas dessus.

Pour l'API, le helper doit créer sa sauvegarde des deux vhosts et son état rollback au moment d'une bascule approuvée ; aucun backup de bascule n'a encore été créé. Pour Office, le vhost actif est `/etc/apache2/sites-enabled/office.devsys.fr.conf` (fichier distinct de `sites-available`) ; il faudra le sauvegarder, ne changer que ses quatre références `DocumentRoot`/`<Directory>`, basculer `office-deploy/current` atomiquement et restaurer la sauvegarde du vhost et l'ancienne cible `office-mvp-pdf-20261125-dfaa435` en cas d'échec. La candidate API précédente est `api-office-mvp-pdf-20261125-a115d5f`.

**Un reload gracieux Apache est global** et relit aussi le vhost login même si ses fichiers ne sont pas modifiés. `apachectl configtest` et `php-fpm8.4 -t` avaient réussi au préflight ; ils devront être refaits juste avant toute bascule. Le GO migration ne couvre pas cette opération.

Pour poursuivre, l'opérateur doit répondre exactement : **`GO BASCULE API OFFICE + RELOAD APACHE GLOBAL`**. Ce GO autorisera les deux bascules dans l'ordre API puis Office, les changements strictement limités aux vhosts API/Office concernés et les reloads globaux nécessaires. Il n'autorisera aucune génération/émission de facture, aucune modification de notes légales réelles, ni aucun changement OIDC/login. La recette authentifiée des paramètres et la conformité réglementaire exhaustive restent hors de ce lot.
