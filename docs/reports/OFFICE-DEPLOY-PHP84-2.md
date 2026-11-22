# OFFICE-DEPLOY-PHP84-2 — Intégration et déploiement contrôlé Office

**Date :** 22 novembre 2026 (Europe/Paris)
**Décision :** **ROLLED_BACK** — déploiement temporaire autorisé par l’opérateur, puis rollback immédiat au premier contrôle non conforme. La version candidate n’est pas validée en HTTP.
**Portée :** code de la branche fonctionnelle et packaging ; lecture seule après déploiement. Écritures facture, scopes d’écriture et migrations exclus.

## Résumé

- Références distantes relues : Office/main 0f9d6e9, Office/feature 470b486, shared/main 83e5b7d, Office-Api/main c976b6a. Main est ancêtre de la branche Office ; feature est 8 commits devant main. Le candidat ajoute un commit packaging local 7f84691, soit 9 commits au-dessus de main.
- Aucun PR Office correspondant n’a été trouvé. Aucun push ni merge n’a eu lieu. Après accord opérateur, l’artefact a été activé brièvement derrière le seul vhost Office ; la sonde locale PHP a répondu 404, sans preuve de SAPI. Conformément au critère d’arrêt, le vhost initial a été restauré et Apache rechargé.
- L’archive reproductible est conservée hors DocumentRoot. SHA-256 : a50cdca6fd246a2a4a26bf7e7282a1e733d7a3e4c40ce2fcd6966e7f83065c01. La release extraite reste inactive pour diagnostic.
- Les flags/scope d’écriture et les données de facturation n’ont pas été modifiés. Le seul changement de configuration persistant est le mode du `.env` Office, passé de 0644 à 0640 conformément à l’autorisation, avec lecture www-data vérifiée.
- L’avis npm HIGH antérieur est corrigé dans la branche de travail par un override Sass limité. Le lock fixe source-map-js 1.2.4 et npm audit retourne zéro vulnérabilité.
- Le `.env` conserve son propriétaire/groupe devsys:www-data et son mode est maintenant 0640 ; le contenu n’a pas été lu. Le processus www-data a été vérifié comme pouvant le lire.

## A — Sources, intégration et runtime

### Références actualisées

| Dépôt | Référence | SHA |
|---|---|---|
| lgimet/office | main | 0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e |
| lgimet/office | feature/office-ui-inv-1.1 | 470b486949ca5c7d74e29169c561f434490ae6fa |
| lgimet/office | work/office-deploy-php84-2 après correctif | 7f84691e861ee84a86bbcab35b3c42f5766fbcfb |
| lgimet/shared | main | 83e5b7da32ae20039218586e18dc93ee9ad95852 |
| lgimet/shared | feature/office-shared-inv-2-1 | 0b535cb28de2393d38c25d766967514b96d22b3e |
| lgimet/Office-Api | main, consultation seule | c976b6a8e3722aca63fcbe3c73e6e6ea836584cf |
| lgimet/Office-Api | feature/office-api-inv-3 | 7b0bd864a7f10b1b0c82272dc246d5f0e8c4eff9 |

La branche shared fonctionnelle est à un commit derrière main ; la branche API fonctionnelle est à quatre commits derrière main. Aucun code n’a été changé dans ces deux dépôts.

Le clone Office propre est issu de feature/office-ui-inv-1.1 et main en est l’ancêtre direct. Avant correctif packaging, le diff main→feature portait sur 38 fichiers, 3 276 insertions et 97 suppressions. La branche de travail actuelle a un commit supplémentaire qui modifie seulement composer.json, package.json et package-lock.json.

### Changements de feature à revoir avant PR

Le bloc UI-INV-2.1 dépend du client API, du fournisseur de jeton OIDC serveur, des services de lecture/brouillon, du contexte tenant côté serveur et des adaptations du contrôleur, du formulaire Twig et de JS. Les composants OAuth/client génériques doivent être relus comme prérequis ; ne pas élargir l’architecture ni les scopes.

Éléments non directement liés à la facture, présents dans le diff de feature :

- docs/postman/office-api.postman_collection.json retire la requête POST « Créer un client » (50 lignes) ; c’est hors facture et modifie la collection.
- docs/authentication-utilisateur.md supprime un lien vers la documentation d’authentification API ; ce changement n’est pas requis par le formulaire facture.
- public/assets/logo.png et public/assets/logo-vert.png sont deux nouveaux fichiers identiques de 9 554 octets à la racine des assets. Aucun usage de ces chemins n’a été trouvé ; les vues utilisent les logos sous public/assets/images/.
- src/Controller/PreviewController.php, le client API, OAuth/JWT et leurs DTO/services sont génériques ; justifier leur nécessité dans la PR.
- Plusieurs rapports UI/recette/préflight sont ajoutés dans docs/reports ; les conserver comme documents, sans les confondre avec le code runtime.
- .env.example, config/services.php, les hooks JS, vues, services InvoiceRead/Draft et leurs tests sont directement liés à la feature ou à ses dépendances.

La comparaison a examiné 264 fichiers suivis présents dans l’arbre servi : 3 fichiers suivis sont absents (.vscode/settings.json, database/003_create_invoices.sql, docs/api/authentication.md). Les trois autres différences sont composer.json/package.json/package-lock.json, correspondant au correctif local. Les deux fichiers CSS buildés sont identiques au serveur. Cela ne transforme pas l’arbre non-Git en preuve d’un SHA complet.

Aucun PR ouvert ou fermé n’a été trouvé. Proposition : PR de la branche fonctionnelle revue, complétée par le commit packaging 7f84691, après traitement des différences non facture ci-dessus. Aucun push n’a été fait ; la branche de travail est locale dans /home/devsys/office-deploy/worktrees/office-deploy-php84-2-office, hors DocumentRoot et hors de l’arbre applicatif API.

### Chaîne Apache/FPM et application

| Élément | Constat |
|---|---|
| Vhost Office HTTP/HTTPS | /etc/apache2/sites-enabled/office.devsys.fr.conf, DocumentRoot /home/devsys/office/public. |
| Handler local | SetHandler PHP 8.0 uniquement en commentaire ; aucun pool Office dédié. |
| Handler actif | /etc/apache2/conf-enabled/php8.4-fpm.conf applique proxy_fcgi et /run/php/php8.4-fpm.sock aux scripts PHP. |
| Pool/service | php8.4-fpm.service actif, pool www, utilisateur/groupe www-data ; socket présent. PHP 8.0-FPM est également actif et ne doit pas être arrêté. |
| Autres hôtes | api.devsys.fr sert /home/devsys/api/public et hérite aussi du handler global PHP 8.4. Des vhosts SpotCard/MenuFlow/API sont actifs ; aucun n’a été modifié. |
| Entrée/config | public/index.php charge vendor/autoload.php, puis Dotenv depuis la racine de l’arbre (../.env). |
| SDK actif | vendor/devsys/shared est un symlink mutable vers /home/devsys/shared, sans Git local. Il ne sera pas utilisé par l’artefact. |
| Persistance | OFFICE_STORAGE_DIR=/var/lib/office (0750 www-data:www-data) ; var/cache existe en 0700 www-data:www-data ; logs Apache sous /home/devsys/log/. Aucun current ou releases Office n’existe. |
| Sessions | php-fpm8.4 -i indique session.save_path=/var/lib/php/sessions ; répertoire système, hors release. |
| .env | devsys:www-data, mode 0640 après action autorisée. Contenu non lu sauf flags autorisés ci-dessous ; lecture www-data vérifiée. |

La configuration directe initiale ne passait pas par une release immuable. Pendant l’essai autorisé, seul le vhost Office a temporairement pointé vers `/home/devsys/office-deploy/current/public`; le handler global n’a pas changé. Après le 404 de la sonde, le vhost original a été restauré octet pour octet depuis la sauvegarde, `apachectl configtest` a renvoyé Syntax OK, puis seul `apache2.service` a été rechargé. Aucun service FPM n’a été redémarré/rechargé.

La version PHP d’une requête HTTP réelle reste UNKNOWN : la sonde loopback a répondu HTTP 404 au lieu de livrer la valeur SAPI. Le fichier sonde a été supprimé et son absence vérifiée. La version PHP HTTP n’est donc pas validée.

### Flags, scopes et écritures

Valeurs lues dans .env Office : OFFICE_INVOICE_READ_API_ENABLED=true, OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false. Les variables API INVOICE_WRITES_ENABLED et INVOICE_WRITE_SOURCES sont absentes de .env et des environnements de processus examinés ; le code Office-Api/main a pour défaut false et liste vide. Aucune variable n’a été changée. Aucun scope write:invoices n’a été ajouté/demandé. Aucune requête vers l’API n’a été faite.

Tous les flux d’écriture facture restent désactivés. Aucun POST/PATCH/issue/delete, numéro, migration, restauration ou changement de données n’a eu lieu.

## B — Release reproductible

### Identifiants et conservation

- Artefact : /home/devsys/office-deploy/artifacts/office-php84-20261122-7f84691-83e5b7d.tar.gz
- SHA-256 archive : a50cdca6fd246a2a4a26bf7e7282a1e733d7a3e4c40ce2fcd6966e7f83065c01
- Taille : 567 531 octets.
- Répertoire persistant privé, hors /home/devsys/office/public et /home/devsys/api/public, mode 0750 devsys:www-data ; archive mode 0640 devsys:www-data.
- Office source : commit local 7f84691e861ee84a86bbcab35b3c42f5766fbcfb, parent feature 470b486… non fusionné à main.
- Shared source : 83e5b7da32ae20039218586e18dc93ee9ad95852, copie embarquée shared/. Le lock référence ce SHA exact comme path distribution.
- Outils : PHP CLI 8.4.24, Composer 2.10.2, Node v22.23.1, npm 12.1.0.

Le manifeste release ajuste le repository Composer à path: shared avec symlink:false ; l’installation copie shared dans vendor. Le workflow dev Office reste inchangé (../shared, symlink true).

L’archive contient composer.lock, package-lock.json, RELEASE-MANIFEST.md et SHA256SUMS (241 fichiers vérifiés). Elle exclut .git, .env*, storage, var/cache/logs/sessions, fichiers privés/uploads, SQL/base, tests, documentation, vendor, node_modules et anciens .css.map. Les CSS/JS déjà construits sont inclus. L’installation depuis extraction vierge et sans .git shared a confirmé que vendor/devsys/shared est un dossier réel, pas un symlink, et que les classes facture se chargent.

SHA-256 sélectionnés de SHA256SUMS :

- composer.json : 5c1008f588310e5204cbc13593054fd053c96615f11b4f20b752420f77df0a4d
- composer.lock : d19a8f93a85f8108f1b6dc7a8fd5a5a531be344a7fe369ab43fb6f52e90c60b9
- package-lock.json : 095c95b27905431ce63f855136a6f19fff905a803f247a9ee76e07dddfad1356
- dashboard.css : 18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f
- login.css : b119e224cb980478c598a8b8176f5e4db94d58f679313d2a36bff07ef515ef09

package-lock.json est vérifié par SHA256SUMS inclus dans l’archive. npm run build reproduit les empreintes CSS servies.

### Correctif packaging/security local

Commit local 7f84691 (non poussé) contient uniquement :

1. composer.json : ajoute php: ^8.2, déjà exigé indirectement par shared.
2. package.json : nom stable office et override Sass limité source-map-js: ^1.2.2.
3. package-lock.json : version fixée source-map-js 1.2.4 ; Sass reste 1.54.9.

Avant, npm ci installait 1.2.1 et l’avis HIGH GHSA-68fv-2mgg-jv7q (déni de service par offsets de cartes sources). Après correction : 1.2.4, npm audit zéro vulnérabilité. Pas de npm audit fix global ni de mise à niveau générale de Sass.

Le lock de production est dans l’artefact, pas dans le checkout de développement où composer.lock est ignoré. Rebuild depuis les SHAs Office/shared approuvés, ajuster le repo Composer à path local symlink:false, résoudre une fois en staging, conserver archive/lock/checksums ensemble. Sur la release extraite, exécuter uniquement composer install --no-dev --no-interaction --prefer-dist, puis composer check-platform-reqs. Jamais composer update sur une release déployée.

## C — Contrôles effectués

| Vérification | Résultat |
|---|---|
| composer validate --no-check-publish release | PASS ; avertissements non bloquants : licence non déclarée et version racine 1.0.0 par défaut. |
| composer install verrouillé Office avec dev | PASS ; aucune mise à jour après lock. |
| composer check-platform-reqs avec dev | PASS PHP CLI 8.4.24, extensions requises. |
| composer test Office | PASS : 134 tests, 345 assertions, PHP 8.4.24 ; mocks/unités, aucun DSN MySQL. |
| composer test shared/main propre | PASS : 46 tests, 197 assertions ; erreurs legal_type obsolètes non reproduites. |
| Installation production extraction vierge | PASS composer install --no-dev --no-interaction --prefer-dist ; 27 packages, zéro update ; check-platform-reqs PASS PHP 8.4.24. |
| Autoload facture | PASS InvoiceOrigin, InvoiceDetails, InvoicesApi ; signatures get/1, createDraft/2, updateDraft/4 paramètres. |
| php -l Office source/tests | PASS, aucune erreur de syntaxe. |
| npm ci/audit/build | PASS ; audit zéro vulnérabilité, CSS générés identiques au serveur. |
| node --check Core.js, Invoices.js | PASS. |
| git diff --check | PASS sur Office, clone shared propre après retrait du cache PHPUnit, clone API/main propre. |
| Apache/FPM | PASS apachectl configtest, php-fpm8.4 -t ; aucune reload. |
| Tests MySQL/API | NOT_RUN ; aucun DSN actif utilisé. Office/shared n’ont pas de tests MySQL à sauter dans ces suites unitaires ; suite API non lancée car référence en consultation seule. |
| HTTP/OIDC/Twig/PDF | NOT_RUN ; réservé à la fenêtre approuvée, aucune session opérateur utilisée. Aucun résultat HTTP revendiqué. |

## D — Bascule autorisée, arrêt et rollback exécuté

### Diff vhost proposé

DocumentRoot actuel : /home/devsys/office/public. Le symlink current n’existe pas. Cible : /home/devsys/office-deploy/current/public, afin de conserver l’arbre servi intact et permettre un retour par symlink.

Dans les deux blocs du seul fichier /etc/apache2/sites-enabled/office.devsys.fr.conf (HTTP :80 et HTTPS :443), remplacer :

    - DocumentRoot /home/devsys/office/public
    + DocumentRoot /home/devsys/office-deploy/current/public
    - <Directory /home/devsys/office/public>
    + <Directory /home/devsys/office-deploy/current/public>

Ces deux paires (quatre lignes) sont les seules lignes Apache proposées. Aucun SetHandler n’est ajouté ; le handler global PHP 8.4 reste inchangé.

### Bascule tentée et retour arrière

La procédure autorisée a été exécutée sur le seul vhost Office. La release a été extraite dans `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d`, avec `.env` externe lié sans copie et cache lié à l’ancien emplacement. Le DocumentRoot a temporairement ciblé `/home/devsys/office-deploy/current/public`; le pointeur `current` a été activé atomiquement vers la release candidate. La configuration Apache a passé `apachectl configtest`, et seul `apache2.service` a été rechargé. Aucun FPM n’a été redémarré ni rechargé.

La sonde loopback `__office-sapi-probe-20261122.php` a répondu HTTP 404. Elle a immédiatement été retirée ; sa présence a été vérifiée absente. Comme la version HTTP/SAPI n’était pas démontrée, arrêt selon le critère de première défaillance : pointeur remis sur l’arbre `/home/devsys/office`, configuration vhost originale restaurée depuis sauvegarde, `apachectl configtest` réussi, reload Apache seul, puis suppression du symlink `current` pour rétablir son absence initiale. La configuration restaurée est identique octet pour octet à sa sauvegarde.

État observé après rollback : Office `/` 302 ; `/Invoices/data` 401 ; CSS 200 ; JS 200. Contrôles des hôtes pairs : `api.devsys.fr /` 404, `api.spotcard.fr /` 404, `menuflow.fr /` 200. Les services Apache, PHP 8.0-FPM et PHP 8.4-FPM sont actifs. Release et archive conservées, inactive/hors DocumentRoot. Aucune requête mutatrice facture ni aucune migration n’a été exécutée.

Le `.env` a été durci après autorisation : mode initial 0644, mode final 0640, owner/group `devsys:www-data` conservés ; la lecture par `www-data` a été vérifiée. C’est le seul changement persistant en dehors des artefacts privés.

### Sonde et contrôles HTTP

La sonde temporaire loopback n’a pas fourni la version PHP/SAPI : résultat HTTP 404. Aucun `phpinfo`, secret ou contenu de configuration n’a été exposé. Aucune session opérateur OIDC n’a été utilisée ; liste et GET d’une facture historique sur la release n’ont pas été validés. Les contrôles HTTP ci-dessus sont seulement des vérifications de retour à l’état préexistant après rollback, et ne prouvent pas la compatibilité HTTP de la candidate.

### Rollback réalisé

- Le symlink `current` a été repointé atomiquement vers `/home/devsys/office`, puis retiré ; il est absent à l’état final.
- Le vhost a été restauré à l’identique depuis `/home/devsys/office-deploy/backups/office-php84-20261122/office.devsys.fr.conf.before`.
- `apachectl configtest` a renvoyé `Syntax OK`; seul `apache2.service` a été rechargé.
- Requêtes de lecture Office et hôtes pairs vérifiées comme ci-dessus. Aucun FPM, base ou autre vhost n’a été touché.
- Archive et release restent conservées pour diagnostic ; aucun nettoyage/suppression de données n’a eu lieu.

## Exécution autorisée, point d’arrêt et rollback

L’opérateur a répondu « j’autorise » pour les actions de déploiement lecture seule décrites. Cette autorisation a été appliquée à la préparation/extraction, aux liens de configuration/cache, au changement limité du vhost, au reload Apache, à la sonde locale et aux GET de lecture. Elle n’a pas autorisé de publication/merge vers `main`, de changement de scopes/flags ni d’écriture facture.

Aucune PR, push, merge ou modification des dépôts `shared` et `Office-Api` n’a eu lieu. Le vhost Office est revenu à sa configuration initiale. Le seul changement persistant hors release/archive est le mode `.env` 0640 approuvé ; pas d’autre autorisation n’est requise pour documenter ce résultat. Toute nouvelle tentative de bascule ou publication devra faire l’objet d’un accord distinct avec SHA/version et fenêtre clairement indiqués.

## Invariants et suites

Aucune émission, génération de numéro, création/modification/suppression de facture, migration ou restauration n’a été effectuée. Office conserve le flag lecture observé true et le flag brouillon false. Les gates API restent aux défauts OFF/vides ; aucun scope write n’a été demandé.

Après validation Office en lecture seule seulement : lots distincts OFFICE-API-INV-RECETTE-GUARD-1, preuve de restauration isolée du dump office, puis OFFICE-INV-RECETTE-1.2 après autorisation séparée.



## Résultat de la tentative et actions restantes

Le retour HTTP 404 de la sonde ne démontre pas à lui seul une erreur PHP de la release : il peut signifier que le chemin/handler de sonde n’a pas été servi. Il ne fournit toutefois aucune preuve de version PHP effective, et le critère d’acceptation exige cette preuve. La release n’est donc pas qualifiée `DEPLOYED_READ_ONLY_VERIFIED`; elle a été retirée et le statut est **ROLLED_BACK**.

Avant une nouvelle fenêtre, corriger la méthode de sonde en vérifiant d’abord le chemin servi/les règles Apache dans un environnement de préparation, puis refaire une approbation opérateur explicite pour toute nouvelle bascule. La sonde doit rester loopback-only, renvoyer uniquement version et SAPI, et être supprimée immédiatement. Ne pas traiter ce 404 comme autorisation de laisser la candidate en service.

La branche `work/office-deploy-php84-2` reste locale/non publiée ; l’intégration vers `Office/main` et l’examen des différences hors facturation restent à faire par PR distincte. Les contrôles HTTP authentifiés (OIDC, liste, détail historique), les checks après-bascule et la preuve de PHP SAPI effective n’ont pas été réalisés. Les prérequis hors lot demeurent : garde API tenant/principal, restauration isolée du dump `office`, puis recette brouillons distincte avec autorisation propre.
