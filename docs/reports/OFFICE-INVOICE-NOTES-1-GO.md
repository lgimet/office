# OFFICE-INVOICE-NOTES-1-GO

Date : 25 novembre 2026 (CET)  
Verdict : **`ACTIVATED_PENDING_BUSINESS_RECETTE`**. Migration 029 appliquée et vérifiée ; candidates API puis Office basculées avec succès. La recette métier authentifiée des mentions n'a pas été exécutée.

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

## Bascule après GO opérateur

Le point B a été autorisé par le message opérateur **`GO BASCULE API OFFICE + RELOAD APACHE GLOBAL`**. L'ordre a été respecté : API, contrôles, puis Office.

| Application | Release active finale | Source | SHA-256 `SHA256SUMS` | Ancienne release conservée |
|---|---|---|---|---|
| API | `api-invoice-notes1-20261125-3482534-r1` | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `39eb43fec88088342d35dc6a5385f875f3db12edd0c207c8efcfe51f21bebda4` | `api-office-mvp-pdf-20261125-a115d5f` |
| Office | `office-invoice-notes1-20261125-7df0921-r1` | `7df0921245494c97297c198695fc373af2e762ae` | `91b82a64869842a3ecfa447ef2e0aeeebebc8e13403fdbb5b9cf73b08d90f337` | `office-mvp-pdf-20261125-dfaa435` |

L'API a été activée par `deploy-api-only-release.py --profile active --apply --reload-apache`. La sauvegarde helper est `/home/devsys/api-deploy/backups/api-vhost-20261125T131317-1465634-api-invoice-notes1-20261125-3482534-r1/` ; elle contient les deux vhosts API, `STATE.json` et ses checksums. Le pointeur API et les vhosts API ciblés pointent maintenant vers la candidate. Le helper a réussi les tests Apache et PHP-FPM avant la bascule, le configtest après écriture et le reload gracieux global.

Pour Office, seul le fichier actif `/etc/apache2/sites-enabled/office.devsys.fr.conf` a été sauvegardé puis ses quatre chemins `DocumentRoot`/`<Directory>` ont été remplacés atomiquement vers `/home/devsys/office-deploy/current/public`. Sauvegarde : `/home/devsys/office-deploy/backups/office-vhost-notes1-20261125T131442/office.devsys.fr.conf`, SHA-256 `e962f7e313ba63d5576287480c9edcd21e35e2b96df4c36e7ec979bc17384c87`. `office-deploy/current` a été basculé atomiquement vers la candidate. Le configtest Apache puis le reload gracieux global ont réussi. Les versions précédentes et les sauvegardes de vhost sont conservées.

**Deux reloads gracieux globaux Apache** ont été réalisés, un après API et un après Office. Chaque reload relit aussi le vhost login ; aucun fichier login/OIDC n'a été modifié. Apache et PHP-FPM sont actifs, `apachectl configtest` et `php-fpm8.4 -t` réussissent après bascule.

## Vérifications post-bascule et limites

| Contrôle non mutateur | Résultat |
|---|---|
| API `GET /api/v1/health` sur loopback | HTTP 200 |
| `GET /api/v1/invoices/issuer-notes` sans authentification | HTTP 401 |
| Office `/` sans session | HTTP 302, redirection habituelle |
| Office `/Invoices/data` sans session | HTTP 401 |
| Asset `/assets/css/dashboard.css` | HTTP 200 |
| Découverte OIDC `login.devsys.fr/.well-known/openid-configuration` sur loopback | HTTP 200 |
| `current`, DocumentRoots, manifests et checksums | pointent vers les candidates ; sommes vérifiées |
| SDK installé dans la candidate Office | `3da1cabb276e6b0e39a2de2710777cba370b3a49`, conforme au lock |
| Gates persistants relus après bascule | API Factur-X et écritures ON, source `devsys`; Office Factur-X, lecture API et brouillons API ON |
| Migration 029 / six colonnes | enregistrée une fois, colonnes `VARCHAR(2000) NULL`, aucune migration candidate en attente |

Aucun compte utilisateur authentifié n'a été utilisé : le formulaire réel PMD/PMT/AAB n'a pas été soumis ou vérifié en session (`NOT_RUN`). Aucune note commerciale/légale n'a été renseignée, aucune facture créée/émise, aucun numéro consommé, aucun PDF/XML généré ou téléchargé. La conformité réglementaire française exhaustive n'est pas revendiquée.

Le dernier état observé de `devsys-backup.service` reste succès, exit 0, le 25 novembre 2026 à 03:26:23 CET ; aucune sauvegarde/restauration n'a été déclenchée pendant cette intervention. Le contrôle Office Composer signale toujours 9 avis de sécurité préexistants sur Twig verrouillé ; aucune dépendance n'a été modifiée dans ce lot.

## État final

Les sources servies sont les deux candidates ci-dessus ; 029 est cohérente sur la base `office`, les gates sont conservés, l'authentification non sessionnée est refusée et les hôtes témoins répondent. Les anciennes releases sont préservées pour rollback applicatif. Le code peut gérer les notes PMD/PMT/AAB, mais leur configuration et leur recette métier restent à faire par un utilisateur légitime, avec les textes approuvés par l'émetteur. La migration additive et ses colonnes ne doivent pas être supprimées lors d'un rollback de code.
