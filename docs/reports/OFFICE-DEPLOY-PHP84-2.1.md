# OFFICE-DEPLOY-PHP84-2.1 — Diagnostic de la sonde PHP et préparation de reprise

**Date :** 22 novembre 2026 (Europe/Paris)
**Décision :** **READY_FOR_OPERATOR_REDEPLOY_APPROVAL** — le chemin HTTP→Apache isolé→PHP-FPM 8.4 est démontré sur la release inactive. Aucune bascule n’a été effectuée dans ce lot. La cause immédiate du 404 précédent est établie ; la raison historique pour laquelle le fichier était absent au chemin FPM n’est pas récupérable avec certitude.

## Résumé exécutif

Le 404 du 22 novembre à 04:38:55 CET n’est pas une preuve d’une panne PHP 8.4 ni d’un 404 du routeur Office. L’entrée correspondante est dans le journal d’accès propre au vhost Office. Le journal d’erreur, à la même seconde, contient une erreur FastCGI/PHP (`AH01071`) : PHP signale ne pas pouvoir ouvrir le script primaire `/home/devsys/office-deploy/current/public/__office-sapi-probe-20261122.php`, avec `No such file or directory`. PHP-FPM a donc été atteint, mais n’a pas pu ouvrir le script ; `public/index.php` et le routeur Office ne se sont pas exécutés.

La configuration initiale de la requête avait un Host et un SNI corrects : `curl --resolve office.devsys.fr:443:127.0.0.1 https://office.devsys.fr/<URI>` conserve `office.devsys.fr` comme nom d’URL tout en envoyant le trafic à loopback. L’absence de `-L` signifie que le 404 observé était la réponse directe. L’accès est journalisé dans `access-office.log`, et l’erreur PHP/FastCGI dans `error-office.log` : le vhost Office a répondu, pas le vhost HTTPS par défaut.

Un Apache éphémère strictement lié à `127.0.0.1`, utilisant le socket FPM 8.4 existant et la release inactive, a répondu : CSS `200`, sonde PHP `200` avec `8.4.24` et `fpm-fcgi`. Le test a aussi réussi avec un DocumentRoot passant par un symlink. Un fixture séparé et inerte a vérifié la règle `.htaccess` de réécriture vers un contrôleur. Les sondes et fichiers temporaires ont été supprimés et leur absence vérifiée.

L’écart historique résiduel est la cause matérielle de l’absence au chemin `current/public` à l’instant du GET : les logs ne prouvent pas si le fichier sonde a été créé ailleurs, si le pointeur ne visait pas encore la release attendue, ou si une course de séquencement a existé. Les droits actuels et un test symlink→FPM réussi ne permettent pas de départager ces hypothèses après coup.

## Références et état des dépôts

Références distantes actualisées par `git ls-remote` au début de ce lot :

| Dépôt | Référence | SHA |
|---|---|---|
| `lgimet/office` | `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` |
| `lgimet/office` | `work/office-deploy-php84-2` | `02fdfb7758efb51f87333c904826e9c7b9cd7dad` |
| `lgimet/shared` | `main` | `83e5b7da32ae20039218586e18dc93ee9ad95852` |
| `lgimet/Office-Api` | `main` | `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` |

Le checkout local `shared` et `/home/devsys/api` n’ont pas de métadonnées Git utilisables ; le premier n’a pas de remote `origin`. Les références ci-dessus proviennent d’une interrogation distante directe pour `shared`, Office et API. Le SHA de la source de l’artefact Office reste `7f84691e861ee84a86bbcab35b3c42f5766fbcfb`, basé sur la branche fonctionnelle `470b486949ca5c7d74e29169c561f434490ae6fa`; il n’est pas le SHA de `main` intégré. Après le push du rapport 2.0, la tête distante `work/office-deploy-php84-2` est `02fdfb7…` et contient son rapport final. Ce rapport 2.1 corrige explicitement son ancienne phrase indiquant que la branche 2.0 était seulement locale.

Le rapport est préparé dans le worktree Office `/home/devsys/office-deploy/worktrees/office-deploy-php84-2.1`, branche dédiée `work/office-deploy-php84-2.1` issue de `02fdfb7…`. Aucun push, PR, merge ou changement de `main` n’a été fait dans ce lot.

## Preuves du 404 du lot 2.0

| Contrôle | Constat | Qualification |
|---|---|---|
| Entrée d’accès | `22/Nov/2026:04:38:55 +0100`, `GET /__office-sapi-probe-20261122.php`, statut `404`, journal `access-office.log` | PASS — réponse enregistrée par le vhost Office |
| Entrée d’erreur corrélée | `22/Nov/2026 04:38:55`, `AH01071`, avertissement de démarrage PHP « Failed to open stream » puis « Unable to open primary script … (No such file or directory) » | PASS — erreur émise au passage FastCGI/PHP ; le chemin `SCRIPT_FILENAME` demandé est le chemin sous `current/public` |
| Vhost/TLS | URI appelée par HTTPS avec `--resolve office.devsys.fr:443:127.0.0.1`; journal propre à Office | PASS — Host et SNI correspondent à Office ; aucune redirection n’a été suivie |
| Exécution du routeur Office | Le script demandé n’a pas pu être ouvert ; aucun indice d’exécution de `public/index.php` | PASS — ce 404 ne vient pas d’une route applicative Office |
| Cause sous-jacente du chemin absent | Le journal établit le chemin demandé, pas l’état exact du symlink ni le lieu de création du fichier à cette milliseconde. L’historique de commandes de cette requête ne suffit pas à reconstituer ces détails. | UNKNOWN — hypothèses possibles : fichier placé hors du chemin visible via `current`, pointeur pas encore sur la cible prévue ou course de séquencement |

Le log corrélé a été lu avec masquage des informations non nécessaires ; aucun cookie, en-tête Authorization, secret, contenu de facture ou donnée personnelle n’a été recueilli dans ce rapport. Les journaux originaux n’ont pas été modifiés ni purgés.

## Routage et état actuels

| Élément | Vérification lecture seule |
|---|---|
| Vhost Office | `office.devsys.fr` est déclaré sur `*:80` et `*:443`; le vhost TLS Office est distinct du vhost par défaut `api.devsys.fr` |
| DocumentRoot actif | `/home/devsys/office/public` dans les blocs HTTP et HTTPS |
| `<Directory>` | `AllowOverride All`, `Require all granted` sur le DocumentRoot Office actif |
| Handler PHP | Le handler local PHP 8.0 demeure commenté ; le handler global actif pointe vers `/run/php/php8.4-fpm.sock` |
| Modules utiles | `rewrite`, `proxy`, `proxy_fcgi`, `headers`, `authz_core`, `authz_host`, `dir`, `alias` chargés |
| État rollback | `current` absent ; le vhost actif est identique octet pour octet à sa sauvegarde du lot 2.0 ; aucune sonde résiduelle |
| Services | Apache et PHP 8.4-FPM actifs ; aucun reload/restart effectué pendant ce lot |
| Permissions release | Les répertoires de release sont traversables par `www-data`, `public/index.php` lisible ; FPM a pu lire/exécuter la sonde dans le test isolé |
| `.env` | Mode `0640`, propriétaire/groupe `devsys:www-data`, valeur persistante issue du lot 2.0 ; contenu non lu dans ce lot |

Le `.htaccess` est présent dans l’arbre Office actif et dans `public/.htaccess` de l’archive. Les checksums et l’extraction vérifiés au lot 2.0 restent associés à l’archive. Ses règles ont cet ordre : les URL finissant par `.js` sont réécrites avant le test `REQUEST_FILENAME -f`; les autres fichiers existants sont servis directement, les dossiers existants aussi, et les chemins restants vont au front controller. Ainsi un fichier PHP existant n’est pas réécrit par cette règle de présence, sous réserve que le fichier soit accessible. `AllowOverride All` autorise l’application de ces règles pour l’arbre configuré.

## Release et preuve sûre HTTP/SAPI

- Archive présente : `/home/devsys/office-deploy/artifacts/office-php84-20261122-7f84691-83e5b7d.tar.gz`.
- SHA-256 recalculé : `a50cdca6fd246a2a4a26bf7e7282a1e733d7a3e4c40ce2fcd6966e7f83065c01` (conforme au SHA attendu).
- La release extraite reste présente et inactive, hors DocumentRoot. `vendor/devsys/shared` est un répertoire copié, pas un symlink ; `composer.lock` référence le SHA partagé `83e5b7da32ae20039218586e18dc93ee9ad95852`. `.env` n’est pas dans l’archive ; `.htaccess`, `composer.lock`, `RELEASE-MANIFEST.md` et `SHA256SUMS` y sont présents.
- L’archive n’a pas été reconstruite. Aucun fichier source/packaging n’a changé ; les tests Composer/npm du rapport 2.0 restent les tests du même SHA d’artefact. Ils n’ont pas été relancés.

### Test HTTP isolé réalisé

Un fichier temporaire retournant seulement une marque de test, `PHP_VERSION` et `PHP_SAPI` a été créé dans la racine publique de la release inactive. Un fichier de configuration Apache temporaire, avec DocumentRoot explicitement égal à cette release, a été exécuté sur un port local disponible lié à `127.0.0.1`. Son `<Directory>` n’autorisait que loopback. Il utilisait `/run/php/php8.4-fpm.sock`; aucune configuration active et aucun service FPM n’ont été rechargés. La sonde ne contenait aucun bootstrap Office et n’a pas lu `.env`, la base, une session ou l’API métier.

| Test | Résultat |
|---|---|
| `apache2 -t` sur la configuration temporaire | PASS — `Syntax OK` |
| GET de `/assets/css/dashboard.css`, Host `office.devsys.fr` vers loopback | PASS — HTTP 200 depuis la release candidate |
| GET d’un fichier PHP existant dans la release | PASS — HTTP 200, corps limité à la marque attendue, PHP `8.4.24`, SAPI `fpm-fcgi` |
| GET vers un DocumentRoot test en symlinkant la release | PASS — HTTP 200, `8.4.24`, `fpm-fcgi`; le symlink n’est pas en soi incompatible avec FPM |
| Règle front-controller | PASS sur un fixture séparé et inerte : copie du `.htaccess`, `index.php` factice ; un chemin inexistant atteint le fixture par réécriture et retourne son marqueur via FPM |
| Suppression | PASS — sondes, configuration, logs et répertoire de test temporaires supprimés ; absence des sondes vérifiée |

Cette preuve démontre le transport HTTP vers FPM 8.4 et l’exécution PHP de la release sur une configuration isolée. Elle ne prouve pas encore la version SAPI d’une requête via le vhost Office actif ni le bootstrap/OIDC/lecture métier de l’application Office. Le test de fixture ne lance pas l’application Office.

## Flags, écritures et effets de bord

Aucun flag, scope, vhost, service, table, facture, migration, session ou configuration applicative n’a été modifié. Aucun POST/PATCH/issue/delete, aucune émission/numérotation et aucun appel à l’API facture n’ont été effectués. La valeur runtime actuelle des flags n’a pas été relue dans les environnements de processus ; elle est **UNKNOWN dans ce lot**. Le rapport 2.0 constatait Office `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false` dans le `.env` et les défauts API d’écriture OFF/vides. Ce lot n’a changé aucune de ces valeurs.

Les seules modifications de fichiers de ce lot sont temporaires, dans `/tmp` et sous `public/` de la release inactive ; elles ont toutes été retirées et vérifiées absentes. Les journaux de production ont été seulement lus. Pas de test mutateur ni d’effet tiers.

## Préparation de la prochaine fenêtre — non exécutée

### Précontrôles

1. Vérifier l’accord opérateur spécifique à la nouvelle fenêtre, le SHA d’archive ci-dessus, le DocumentRoot/vhost actif, l’absence de `current`, les flags, les liens `.env`/cache, les permissions et l’intégrité de la release. Ne pas interpréter cet état READY comme autorisation.
2. Sauvegarder le vhost et noter ses empreintes ; tester `apachectl configtest`; confirmer que l’unique reload éventuel sera `apache2.service`. Aucun reload FPM.
3. Avant routage actif, vérifier dans la release que le fichier de sonde est lisible par `www-data`, que `readlink -f current/public` résout vers la release attendue et que `[ -f current/public/<sonde> ]` réussit sous le même pointeur. Après bascule du lien, refaire ces assertions avant HTTP ; si l’une échoue, rollback sans requête.
4. S’assurer que la sonde refuse tout client dont `REMOTE_ADDR` n’est pas `127.0.0.1` ou `::1`, ne retourne que la version PHP/SAPI et répond 404 aux autres clients. Elle doit être dans la release candidate ciblée, nom temporaire, sans `phpinfo()`.

### Assertions HTTP après autorisation distincte

- Pour la vhost Office locale, garder le vrai nom dans l’URL et l’épingler à loopback : `curl --resolve office.devsys.fr:443:127.0.0.1 https://office.devsys.fr/<sonde>`. Ne pas ajouter `-L` à l’appel de sonde ; enregistrer code, Host attendu et SAPI. Un HTTP 200 avec exactement version `8.4.x` + `fpm-fcgi`, et une entrée `access-office.log` correspondante, sont requis. Aucun `AH01071`/« Unable to open primary script » ne doit apparaître pour la sonde. Si la réponse n’est pas exactement celle attendue, supprimer la sonde et rollback immédiatement.
- Supprimer la sonde dès la mesure et confirmer par `test ! -e` sur le chemin de la release, puis refaire un GET de cette URI et confirmer qu’elle ne retourne aucun marqueur PHP. Pas d’accès sonde par adresse publique/DNS externe.
- Vérifier ensuite page Office (302 attendu sans session, selon la référence), CSS/JS (200), session OIDC existante sans changement de scope, liste et GET d’une facture historique uniquement en lecture. Enregistrer les codes effectifs. Ne pas soumettre de formulaire.
- Comparer les GET de référence API, SpotCard et MenuFlow avant/après, sans modification de leurs vhosts. Aucun pool FPM partagé ne doit être redémarré.

### Rollback prêt

Au premier échec de précontrôle, écart PHP/SAPI, 404 non expliqué, 5xx, session/OIDC, assets ou lecture historique : rebasculer atomiquement `current` sur `/home/devsys/office`, restaurer le vhost sauvegardé si modifié, lancer `apachectl configtest`, puis recharger seulement `apache2.service` si le vhost avait changé. Vérifier `/` (réponse initiale 302), `/Invoices/data` (401 sans session), CSS/JS (200), les hôtes pairs et l’absence de sonde. Aucun FPM, DB ou donnée de facture n’est concerné par le rollback.

## Checklist et décision

| Élément | État |
|---|---|
| Cible précise, vhost et réponse FPM du 404 précédent | PASS pour le vhost et l’échec d’ouverture PHP ; cause historique exacte de l’absence du fichier non récupérable |
| Méthode HTTP isolée de contrôle `PHP_VERSION`/`PHP_SAPI` | PASS — release directe et symlink testés contre FPM 8.4 |
| Archive/source/package | PASS — SHA archivé vérifié, copie `shared` figée, release inactive |
| État actif revenu au baseline | PASS — vhost identique à sauvegarde, `current` absent, sonde absente |
| Tests d’application via HTTP réel, session OIDC, liste/détail historique | NOT_RUN — nécessitent une nouvelle fenêtre autorisée et une session opérateur |
| Flags runtime effectifs | UNKNOWN — aucune lecture/runtime assertion dans ce lot |
| Écritures facture, scope ou migration | PASS — aucune exécutée |
| Déploiement/reload/merge/push | PASS — aucun exécuté dans ce lot |

**Décision finale : `READY_FOR_OPERATOR_REDEPLOY_APPROVAL`**, limitée à une nouvelle bascule Office en lecture seule, avec l’artefact SHA ci-dessus et le test HTTP de sonde corrigé. Cette décision n’autorise ni bascule, ni reload, ni écriture facture, ni publication/merge. L’opérateur devra fournir un accord distinct indiquant fenêtre, SHA et assertions autorisées. Si les précontrôles de pointeur/fichier/permissions ne passent pas au moment de la fenêtre, arrêter et garder le vhost initial.
