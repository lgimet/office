# OFFICE-INVOICE-DEPLOY-1 — Rapport de préparation Office

Date : 22 novembre 2026
Décision : **BLOCKED_SPECIFIC** (candidat préparé, aucun déploiement)

## Périmètre et références

Aucune bascule, modification de `.env`, de symlink actif, de vhost, de service ou de base n'a été effectuée. Aucun appel HTTP de production n'a été lancé.

| Dépôt / référence | SHA observé | État |
|---|---|---|
| Office `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` | inchangée dans cette préparation |
| Socle Office 2.2 | `1333e34aac9f1738afaf795efdcb77044be163e9` | parent fonctionnel conservé |
| Office fonctionnel 1.2 | `64b6b298c6dc049c06b671e02e813b93255da090` | PR #2 ouverte, draft, mergeable vers 2.2 |
| API `main` | `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` | lecture seule |
| API numérotation 1.1 | `227cc0feec6024c068705bc2445c0f77083f3454` | PR #3 ouverte, draft, mergeable |
| shared `main` | `83e5b7da32ae20039218586e18dc93ee9ad95852` | lecture seule |
| shared business-reference 1.1 | `6b99578cdc604d4363091d1cbe7d2115be1d88f9` | PR #3 ouverte, draft, mergeable |

PR Office #2 : https://github.com/lgimet/office/pull/2 — base `work/office-deploy-php84-2.2`, tête `feature/office-inv-recette-1.2`. Elle n'est pas une PR vers `main`. Les trois PR sont ouvertes, draft, mergeables et sans status checks publiés au relevé.

## Candidat Office et dépendance SDK

- Archive de revue : `/tmp/office-invoice-deploy-1/artifacts/office-web-invoice-64b6b29.tar.gz`
- SHA-256 : `df029c66abd32c359e17c107078f71f73f9a0fb0919a29bbc3612010d91484f8` (47 700 715 octets)
- Source Office : `64b6b298c6dc049c06b671e02e813b93255da090`.
- `composer.lock` SHA-256 : `ce2ba7dea44f6a18b5b9d83eddb6b5e490f13aa8c959d9dc8e2dadd415a40676`.
- `devsys/shared` est figé dans le lock sur `0661b92b914c28c41f45f5bd19bb9a860c232676`. Le paquet a été installé en répertoire réel, sans symlink mutable. Le lock fait référence à la branche fonctionnelle publiée : avant production, la PR shared doit être revue/mergée et le mécanisme doit être conservé sur un commit/tag immuable approuvé.
- PHP 8.4.24, Composer 2.10.2 ; assets construits avec Node 22.23.1 / npm 12.1.0.
- CSS dashboard SHA-256 `18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f`; CSS login `b119e224cb980478c598a8b8176f5e4db94d58f679313d2a36bff07ef515ef09`.
- `SHA256SUMS` vérifié. Aucun `.env`, `.git`, tests, `node_modules`, stockage, logs, sessions ou uploads n'est inclus. Les valeurs runtime ne sont pas embarquées.
- Le candidat reste sous `/tmp`, donc temporaire et non adapté à la conservation de release. Ne pas le présenter comme un artefact prêt à installer depuis un dépôt pérenne.

## Configuration actuellement observée

- Le vhost Office sert via `/home/devsys/office-deploy/current/public`. Le pointeur actif se résout vers `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d` ; il n'a pas été modifié.
- Le manifeste de cette release actuelle rapporte Office source `7f84691e…`, shared `83e5b7da…`, PHP-FPM 8.4.24. Le `.env` est hors release, fourni par lien externe ; son mode observé est `0640` et il n'a pas été lu ni modifié.
- Valeurs Office constatées par lecture ciblée : `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, `OFFICE_INVOICE_READ_API_ENABLED=true`.
- L'API actuellement servie lit `INVOICE_WRITES_ENABLED` absent et `INVOICE_WRITE_SOURCES` absent dans sa configuration observée ; les défauts applicatifs sont false et liste vide. Aucun flag n'a changé. Les valeurs effectives restent à revérifier immédiatement avant toute future bascule.
- Apache et PHP-FPM 8.4 étaient actifs ; `apachectl configtest` et `php-fpm8.4 -t` ont réussi sans reload. Le rapport ne prétend pas qu'une requête HTTP après cette version ait été mesurée ici.
- Le backup quotidien a un run réussi le 22/11/2026 vers 03:20 CET ; une preuve de restauration spécifique à la base `office` reste distincte et non obtenue.

## Tests exécutés sur copies isolées

| Contrôle | Résultat |
|---|---|
| `composer validate --no-check-publish` | PASS ; avertissements de licence/version racine |
| `composer check-platform-reqs` | PASS sous PHP 8.4.24 |
| `composer test` | PASS — 138 tests, 384 assertions |
| `npm ci --no-audit --no-fund` / `npm run build` | PASS |
| `node --check public/assets/js/Object/Invoices.js` | PASS |
| `php -l` contrôleur et service de brouillon modifiés | PASS |
| `composer audit --locked --no-dev` | PASS — aucun avis sur le lock Office construit |
| `git diff --check` | le diff du nouveau rapport sera vérifié après ajout ; les anciens rapports hérités ont des espaces finaux préexistants |
| Tests HTTP/OIDC/factures | NOT_RUN dans ce lot ; aucun GO de bascule et aucun probe production |

Le lock du dépôt `shared` lui-même résout Guzzle 7.14.2, pour lequel `composer audit` signale cinq avis. Le lock de cette release Office résout Guzzle 7.16.5 et son audit est propre. Cette différence vient du lock consommateur ; elle doit être prise en compte avant qu'un autre consommateur installe le SDK avec son propre lock. Aucun `composer update` ni changement de shared n'a été effectué ici.

Une tentative initiale des tests Office a échoué car un `vendor` partiel manquait `myclabs/deep-copy`; après une installation verrouillée propre, la suite complète a réussi sans warnings. Cette tentative transitoire n'a pas modifié l'arbre actif.

## Plan de bascule et retour arrière — non exécuté

Une future fenêtre exige d'abord l'approbation explicite du SHA/artefact, du chemin et du changement limité à Office. Capturer le checksum et la valeur actuelle du pointeur `current`; conserver le `.env` externe et les répertoires persistants. Installer dans un répertoire de release distinct, vérifier propriétaire/droits et l'intégrité, puis déplacer atomiquement le pointeur `current` vers la release approuvée. Aucun changement de FPM global ou de vhost ne semble requis d'après la configuration observée, mais cette conclusion doit être revalidée au moment de la fenêtre.

Rollback : replacer atomiquement `current` vers l'ancienne release ci-dessus ; si le vhost a dû changer, restaurer uniquement sa copie contrôlée ; exécuter `apachectl configtest` et recharger Apache uniquement si sa configuration a changé. Ne pas redémarrer FPM, ne pas restaurer la base. Vérifier ensuite les parcours Office et hôtes témoins.

À la validation future : vérifier HTTP effectif PHP 8.4, login/OIDC existant, dashboard, assets, liste/détail de factures en GET et autres pages. L'absence de session autorisée donnera un résultat partiel, sans prétendre à une validation authentifiée. Les écritures restent OFF ; aucune émission/suppression n'est incluse.

## Décision et actions restantes

**BLOCKED_SPECIFIC** pour le déploiement coordonné :

1. La release API candidate ne peut pas remplacer sans réconciliation l'arbre API actif divergent (140 entrées de statut). L'état du schéma 024 n'a pas de preuve SQL indépendante ; ne pas réexécuter cette migration.
2. Le code API candidate sélectionne `business_reference`; déployer Office avant une API compatible et une confirmation de 024 exposerait des erreurs de lecture.
3. PR shared #3 et API #3 restent draft, et aucun tag immuable SDK n'a été publié dans ce lot. Office lock fixe déjà le commit SDK exact, mais la publication coordonnée doit préserver cette identité.
4. Les artefacts sont temporaires sous `/tmp` et doivent être déposés dans un espace durable approuvé.
5. Aucun GO nouveau de déploiement API/Office, des SHA, fenêtre et changements exacts n'a été reçu.

Ordre requis : résoudre/reconcilier l'API active et confirmer 024 sans mutation ; revue de sécurité dépendances API ; intégrer/figer shared ; intégrer Office dans la filiation 2.2 ; reconstruire et conserver les artefacts ; valider sauvegarde/rollback ; obtenir un GO séparé pour API puis Office. Aucun merge n'a été fait.

État final : aucune bascule, aucun changement de flags/scopes, aucune migration, aucune requête HTTP de production et aucune écriture de facture. Une future recette de brouillons nécessite encore un GO distinct.
