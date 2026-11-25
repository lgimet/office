# OFFICE-DEPLOY-PHPFPM-RELOAD-1

Date: 2026-11-25  
Verdict: `READY_FOR_REVIEW`

## Diagnostic

HEADs rafraîchis : Office `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`, Office-Api `34825341bab7ed1a8cc16bc6bf7f04bef2427f73`, shared `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf`. Le pointeur API observé résout vers `api-invoice-notes1-20261125-3482534-r1`. Aucun code API/shared n’a été modifié.

L’Office servi pointe par `/home/devsys/office-deploy/current` vers `office-invoice-notes1-20261125-7df0921-r1`; le vhost actif `/etc/apache2/sites-enabled/office.devsys.fr.conf` cible `current/public`. Le handler Apache global envoie les scripts PHP vers `/run/php/php8.4-fpm.sock`, pool `www`, sous `php8.4-fpm.service`. L’API utilise également le handler PHP 8.4 global. Le service héberge plusieurs pools (SpotCard, MenuFlow, taxi/station et `www`) : son reload n’est pas Office-only.

Le FPM-SAPI/config observé via `php-fpm8.4 -i` a OPcache actif, timestamp validation activée, `revalidate_freq=2`, `revalidate_path=Off`, realpath cache `4096K` / TTL `120`. Les fichiers des pools ne surchargent pas ces paramètres. Cette lecture ne sonde pas les variables d’un worker HTTP individuel.

L’opérateur rapporte qu’un reload PHP-FPM après le reload Apache a résolu le formulaire Société ancien. Le journal systemd confirme un reload FPM réussi le 25 novembre à 13:51:32 CET. C’est une corrélation opérationnelle utile, pas une preuve que le realpath cache, OPcache, ou un autre état worker était la cause racine. La reproduction avec des workers FPM distincts n’a pas été exécutée, car elle aurait nécessité un reload de production ou un environnement FPM isolé non disponible. Aucun test authentifié du formulaire n’a été réalisé.

## Changements proposés

- `docs/operations/office-release-switch.md` définit une séquence Office-only : bascule atomique `current`, validations Apache et FPM, point de GO distinct avant reload gracieux du service partagé, vérifications de santé et rollback avec reload FPM après retour applicatif.
- `tests/Unit/OfficeReleaseSwitchRunbookTest.php` vérifie l’ordre des étapes, l’approbation avant reload, la portée multi-pool, le contrôle santé après reload, le reload après rollback et l’absence de `restart` brutal.

Aucun script Office-only de bascule n’a été trouvé dans le `main` Office contrôlé ; le changement fournit donc le runbook opérationnel manquant et un test statique de son ordre. Aucun service, vhost, lien `current`, cache, release ou fichier de configuration de production n’a été modifié. Aucun reload Apache ou PHP-FPM n’a été lancé dans ce lot. Aucune action n’a été faite sur la PR Office #26 (`OFFICE-INVOICE-NOTES-1-UI-FIX`), qui n’est pas un prérequis de cette procédure.

## Contrôles observés

| Contrôle | Résultat |
|---|---|
| `git fetch --all --prune` Office/API/shared | PASS pour la mise à jour de `origin/main`; fetch Office a aussi signalé une ancienne branche distante absente, sans empêcher la mise à jour de `main` |
| `php-fpm8.4 -t` | PASS — configuration FPM valide |
| `php-fpm8.4 -i` ciblé | PASS — FPM SAPI/OPcache/realpath relevés |
| `composer test` | ÉCHEC D’ENVIRONNEMENT — commande lancée, mais `phpunit: not found` dans ce checkout. Aucune dépendance n’a été installée |
| Test ciblé `OfficeReleaseSwitchRunbookTest` | PASS — 1 test / 15 assertions via PHPUnit 10.5.64 disponible dans un autre checkout, sans écrire son cache de résultats |
| `npm run build` | PASS |
| `git diff --check` | PASS |
| `systemctl is-active apache2 php8.4-fpm` | PASS — les deux actifs |
| `office.devsys.fr/` | HTTP 302 attendu |
| `api.devsys.fr/api/v1/health` | HTTP 200 |
| `login.devsys.fr/.well-known/openid-configuration` | HTTP 200 |
| Paramètres Société authentifiés après reload | `NOT_RUN` — aucune session légitime disponible |
| Reload de validation ou bascule en production | `NOT_RUN` — expressément hors périmètre |

## Portée et action de déploiement future

La procédure exige un accord explicite pour le reload de `php8.4-fpm.service`, qui renouvelle les workers de tous ses pools. Il faut donc prévoir les vérifications des hôtes API et témoins, en plus d’Office. Un éventuel reload gracieux Apache reste global et relit login, même si aucun fichier login n’est édité. La cause interne exacte du défaut de workers reste à confirmer ; le reload FPM est documenté comme mesure opérationnelle empirique, pas comme preuve OPcache spécifique.

La recette authentifiée doit vérifier les trois IDs `company-invoice-note-pmd`, `company-invoice-note-pmt` et `company-invoice-note-aab` dans le DOM sans enregistrer de valeur. Si elle ne peut pas être exécutée, le statut reste `NOT_RUN`; le rendu CLI et la présence des champs Twig ne suffisent pas à valider FPM.
