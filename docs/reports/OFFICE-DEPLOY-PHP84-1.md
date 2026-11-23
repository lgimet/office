# OFFICE-DEPLOY-PHP84-1 — Préparation release Office PHP 8.4

**Date de contrôle :** 21 novembre 2026 (Europe/Paris)  
**Décision :** **READY_FOR_OPERATOR_DEPLOY_APPROVAL** pour le candidat décrit ci-dessous uniquement. Cela n’autorise ni déploiement, ni activation des écritures de factures.  
**Périmètre :** préparation locale et contrôles en lecture seule ; aucun changement Apache/FPM, flag, scope, base ou facture.

## Résumé exécutif

- La configuration Apache observée ne confirme pas le constat historique « Office sur FPM 8.0 » : le handler PHP 8.0 du vhost est commenté. Le handler Apache global actif dirige les fichiers PHP vers /run/php/php8.4-fpm.sock. PHP-FPM 8.4 est actif, son pool par défaut tourne en www-data et php-fpm8.4 -t ainsi que apachectl configtest réussissent.
- Cette configuration prouve le routage configuré vers FPM 8.4, mais aucune version PHP n’a été exposée ou mesurée via une réponse HTTP. La validation HTTP après approbation reste donc une étape opérateur.
- Les fichiers Office facture principaux sur l’arbre servi ont les mêmes SHA-256 que les fichiers de la branche de fonctionnalité retenue. Le SDK partagé est chargé par symlink mutable ; les trois fichiers facture contrôlés sont identiques aux mêmes fichiers de shared/main. Le SHA de tout l’arbre servi ne peut pas être certifié car les arbres Office/shared déployés n’ont pas de métadonnées Git.
- Un artefact autonome a été préparé à partir de Office feature/office-ui-inv-1.1 et d’une copie figée de shared/main. Son lock Composer contient le SHA exact shared et son dépôt local est en symlink:false. Installation propre, tests et build CSS réussissent sous PHP CLI 8.4.24.
- La branche Office de fonctionnalité est 7 commits en avance sur main et peut être intégrée par fast-forward, mais contient l’ensemble UI-INV-1/1.1/2/2.1 et des rapports/fichiers associés. Aucun merge ni déploiement n’a été fait.
- Une vulnérabilité npm de sévérité HIGH apparaît dans une dépendance de build transitive source-map-js ; elle n’est pas incluse dans l’artefact runtime (node_modules est exclu). Elle est documentée pour décision de sécurité avant toute nouvelle construction.

## P1 — Sources, versions et runtime

### Références Git consultées

| Dépôt | Référence | SHA observé | Preuve / limite |
|---|---|---|---|
| lgimet/office | main | 0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e | Référence distante actualisée par fetch dans le clone de préparation. |
| lgimet/office | feature/office-ui-inv-1.1 | d32b0e0aa4f24d77ef399ba0f0be257bfac6a8a1 | Checkout de préparation, branche work/office-deploy-php84-1 créée dessus. |
| lgimet/shared | main | 83e5b7da32ae20039218586e18dc93ee9ad95852 | Clone propre testé. |
| lgimet/shared | feature/office-shared-inv-2-1 | 0b535cb28de2393d38c25d766967514b96d22b3e | Référence fonctionnelle disponible ; derrière main de 1 commit selon les références consultées. |
| lgimet/Office-Api | main | c976b6a8e3722aca63fcbe3c73e6e6ea836584cf | Référence distante consultée ; aucun changement ni déploiement API dans ce lot. |
| lgimet/Office-Api | feature/office-api-inv-3 | 7b0bd864a7f10b1b0c82272dc246d5f0e8c4eff9 | Référence fonctionnelle disponible, derrière main de 4 commits selon les références consultées. |

Office feature contient 7 commits au-dessus de main ; main est ancêtre direct. Le différentiel est de 37 fichiers, environ 3 137 insertions et 97 suppressions. Il inclut les changements UI/API factures, services, tests, documentation, deux logos et une modification/retrait du fichier Postman. Les commits propres couvrent notamment le routage lecture API, la demande de scope lecture, UI-INV-2.1 et le rapport de préflight. Le plan d’intégration est une PR Office feature → main avec revue de ces 7 commits et des 37 fichiers ; aucun merge n’a été exécuté.

Le répertoire Office actuellement servi et le symlink shared ne contiennent pas de .git. Les SHA complets runtime ne sont donc pas démontrables à partir du serveur. Les empreintes suivantes correspondent toutefois exactement entre l’arbre servi et la révision feature candidate :

- src/Services/DevsysInvoiceDraftService.php : efdf86515477cebc6fcca0596e12d834f4b6e597a5308ed0414ebc4ba97f7c82
- public/assets/js/Object/Invoices.js : 467f5650e88dbe02bea3f7d082a26105c7264efa57bf31b22fa66f1b4934d9a4
- src/Controllers/Invoices.php : 4b935141a83dfaed3aa2025e4bd8bc71364ddd64ded1235719c84b8978394616
- composer.json : fa36113b310bb20473179c7760a0355bf552b9d9c2d6ac00c52f67898077b7dc

Le vhost office.devsys.fr pointe vers /home/devsys/office/public. Son SetHandler PHP 8.0 local est commenté. La configuration globale Apache active php8.4-fpm.conf applique proxy_fcgi avec le socket /run/php/php8.4-fpm.sock. Le service php8.4-fpm est actif ; le pool www utilise www-data. Le socket PHP 8.4 est présent. Le vhost api.devsys.fr pointe vers /home/devsys/api/public et hérite lui aussi du handler global. PHP 8.0-FPM est également actif : il ne doit pas être arrêté, car d’autres vhosts peuvent en dépendre.

La configuration Apache et FPM a été testée sans reload : apachectl configtest = Syntax OK ; php-fpm8.4 -t = configuration réussie. Les extensions FPM configurées comprennent notamment curl, gd, intl, mbstring, pdo_mysql, xml et zip. Les exigences Composer du candidat ont été contrôlées sous PHP CLI 8.4.24. La version SAPI réellement vue par une requête HTTP reste à confirmer après une approbation de déploiement ; aucune sonde publique ni phpinfo n’a été ajouté.

### Matrice de compatibilité réelle

| Chaîne | Constat |
|---|---|
| Apache → Office | DocumentRoot du vhost confirmé ; handler 8.0 du vhost commenté, handler global actif PHP 8.4. |
| FPM → Office | Socket PHP 8.4 présent, service actif, pool www/www-data ; test de configuration réussi. |
| Office → shared | vendor/devsys/shared est un symlink vers /home/devsys/shared et reste mutable. L’arbre cible n’a pas .git. Les fichiers InvoiceOrigin.php, InvoiceDetails.php et InvoicesApi.php ont les mêmes empreintes que shared/main (respectivement 164ee0dc677830b7f55df60ada3072fb896adfeec41c55b08c322d1751120408, e47affee501eaa7305424b0c6e4a312509880871f8de4056b013edfaec3adc8d, cf8ed498a76ceda3615d99d37268e378a425fc38db80a93e3097595042045351). Le SHA de l’arbre complet chargé n’est pas démontrable. |
| Office → API | L’API est hors modification. La référence main est c976b6a8… ; l’arbre de déploiement n’est pas certifié à ce SHA par ce lot. Le périmètre PHP d’Office ne requiert aucune bascule API. |

Le chargement PSR-4 de InvoiceOrigin, InvoiceDetails et InvoicesApi a réussi dans l’artefact candidat. InvoiceDetails expose clientUuid, clientUuidProvided et origin ; InvoicesApi expose get, createDraft et updateDraft. Le SDK déclare PHP ^8.2, incompatible avec PHP 8.0 mais compatible avec PHP 8.4.

Le checkout /home/devsys/api n’a pas été utilisé comme source Office : il possède un répertoire Git non standard (.git-office) et de nombreuses modifications préexistantes. Ce lot n’a pas changé de code API. Un essai de résolution Composer mal ciblé a échoué avant installation, bloqué par un avis de sécurité sur firebase/php-jwt ^6 dans ce checkout ; ses composer.json/composer.lock gardent leurs dates préexistantes et aucune dépendance n’a été installée/modifiée. Cet incident n’a pas touché l’arbre servi ni l’artefact Office.

## P2/P3 — État de base et sauvegarde

Aucune connexion ni requête de base n’a été faite pour ce lot. Les migrations 022/023, la sauvegarde spécifique de la base Office-Api et la capacité de restauration restent les constats du rapport OFFICE-INV-PREFLIGHT-1 : ils ne sont pas revalidés ici et ne sont pas nécessaires à cette préparation sans mutation. La restauration isolée du dump Office reste à démontrer avant toute future recette de brouillons ; ne jamais restaurer un snapshot sur une base active.

## P4/P5 — Garde facture, flags, scopes et effets externes

Aucune route POST/PATCH/issue/delete n’a été appelée ; aucune facture ni fixture n’a été créée ou modifiée. Aucune migration, émission, suppression ou restauration n’a été effectuée.

Valeurs de configuration observées dans le fichier Office .env : OFFICE_INVOICE_READ_API_ENABLED=true et OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false. Aucune valeur n’a été changée. Aucun scope OIDC n’a été ajouté et write:invoices n’a pas été demandé. Les flags API n’ont pas été modifiés ; l’injection runtime de variables système n’a pas été auditée dans ce lot. Les effets externes (exports, notifications et intégrations financières), le garde tenant/principal, les scopes temporaires et le contrôle de restauration sont des dépendances des lots hors périmètre OFFICE-API-INV-RECETTE-GUARD-1 et OFFICE-INV-RECETTE-1.2. Aucun feu vert aux écritures de brouillons ou à l’émission n’est donné ici.

## P6 — Artefact et tests reproductibles

### Artefact

Archive préparée : /tmp/office-deploy-php84-rc-20261121.tar.gz  
SHA-256 : 743cf335725d0c4b3a7a9b0f1dee2564709e4b66a86b5c9573233b61295013ce

Elle est issue de l’Office feature SHA d32b0e0aa4f24d77ef399ba0f0be257bfac6a8a1 et inclut shared/main SHA 83e5b7da32ae20039218586e18dc93ee9ad95852 sous shared/. Le composer.lock de l’artefact inscrit ce SHA exact comme référence du dépôt path. Le dépôt embarqué utilise symlink:false ; Composer a installé une copie vendor/devsys/shared, non un lien. L’installation a été vérifiée après retrait du .git embarqué, ce qui montre que l’archive est autonome.

L’archive inclut composer.json, composer.lock, package-lock.json, sources Office/shared et CSS buildés. Elle exclut .git, .env, vendor, node_modules, logs, sessions, uploads, clés et données de base. Le lock et le manifeste SHA256SUMS sont conservés avec l’artefact ; composer.lock reste ignoré par le Git Office source.

Empreintes de l’artefact :

- composer.json : 9462b7a1a14d5c0480142f5831d9eb89dc24d5aa4c5df51c3d92b257c60d9cc5
- composer.lock : 669f202d2aad5fb38ee6d3c204a7a7bde5863a686729b6991ac5667a69237ffb
- package-lock.json : f097aeeb6a2f85796675e1581908d0ccbbfa6a19bd2b3c82e2d519d2b92863d9
- dashboard.css : 18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f
- login.css : b119e224cb980478c598a8b8176f5e4db94d58f679313d2a36bff07ef515ef09

Le build CSS reproduit exactement les empreintes des deux fichiers actuellement servis.

### Commandes et résultats

| Contrôle | Résultat |
|---|---|
| Office composer validate --no-check-publish | PASS ; avertissements non bloquants : licence non déclarée et version racine 1.0.0 utilisée en build. |
| Office composer check-platform-reqs | PASS sous PHP CLI 8.4.24 ; exigences PHP/ext requises satisfaites. |
| Office composer install depuis le lock de l’artefact | PASS ; installation répétée, aucune mise à jour ; shared copié, non symlinké. |
| Office composer test | PASS : 134 tests, 345 assertions, PHP 8.4.24. Tests unitaires/mocks ; pas de DSN MySQL ni test DB de production. |
| Shared composer validate --no-check-publish / check-platform-reqs | PASS, PHP 8.4.24. |
| Shared composer install depuis son lock | PASS. |
| Shared composer test | PASS : 46 tests, 197 assertions. Les deux erreurs legal_type décrites dans le rapport précédent ne se reproduisent pas sur shared/main propre ; ses fixtures fournissent la structure DTO attendue. Aucune règle DTO n’a été élargie. |
| php -l sur sources et tests Office | PASS ; aucune erreur de syntaxe. |
| npm ci | PASS à partir de package-lock.json. Avertissement audit : une vulnérabilité HIGH transitive source-map-js (plage affectée signalée 1.0.0–1.2.1), dépendance de construction. |
| npm run build | PASS ; les CSS générés sont identiques aux CSS servis selon SHA-256. |
| git diff --check | PASS sur les clones Office et shared propres ; aucun changement applicatif laissé dans ces clones hors ce rapport. API : diff --check sans sortie sur le checkout préexistant, mais celui-ci était déjà très modifié et n’est pas une source de build. |
| Suites MySQL, vhost de test, login OIDC HTTP, rendu live Twig/PDF | NOT_RUN ; aucun DSN de production, pas de vhost isolé, pas de session opérateur utilisée. |

Le lock reproductible a été généré dans l’artefact autonome à partir du SDK local figé, après échec de l’indexation Composer VCS GitHub (réponse 404 et fallback SSH indisponible). Aucune mise à jour Composer implicite n’est requise à l’installation : la commande de déploiement est composer install --no-dev --no-interaction --prefer-dist, jamais composer update.

## P7 — Procédure opérateur proposée (aucune action exécutée)

### Avant approbation de déploiement

1. Faire relire et intégrer explicitement les 7 commits Office feature dans main par PR/fast-forward. Reconstruire ensuite l’artefact depuis les SHA approuvés ; recalculer l’archive SHA-256 et la conserver avec la release.
2. Examiner/traiter l’avis npm HIGH source-map-js avant d’exécuter à nouveau npm ci dans un environnement de build ; ne pas lancer npm audit fix automatiquement.
3. Sauvegarder la configuration Apache du seul vhost Office, le DocumentRoot courant, la cible éventuelle de symlink current et les permissions. Préserver le .env séparément, sans le copier dans la release.
4. Préparer une release immuable /home/devsys/office/releases/<identifiant> et un pointeur /home/devsys/office/current. La configuration actuelle ne passe pas par current ; une modification explicite du seul office.devsys.fr.conf serait donc nécessaire pour déplacer le DocumentRoot vers current/public. Faire approuver ce diff séparément.
5. Vérifier dans la release les checksums, l’absence de secrets, les droits d’accès et l’absence de symlink vendor/devsys/shared. Exécuter composer install --no-dev --no-interaction --prefer-dist, puis composer check-platform-reqs.
6. Contrôler apachectl configtest et php-fpm8.4 -t. Le handler PHP 8.4 global existe déjà : ne pas modifier le handler global et ne pas arrêter php8.0-fpm. Aucun restart FPM n’est requis si seul le DocumentRoot/symlink Office change. Si un pool Office dédié était ultérieurement jugé nécessaire, le configurer et le faire approuver dans un changement séparé ; le service php8.4-fpm est partagé et son restart peut affecter d’autres vhosts.
7. Point d’arrêt humain : déployer uniquement après approbation opérateur explicite du diff Office, des SHA/artefacts, de la fenêtre et du plan de rollback. Aucune autorisation de feature flags ou d’écriture de facture ne découle de cette approbation.

### Bascule et vérifications HTTP après approbation

- Déposer l’archive dans la nouvelle release, vérifier sa somme, exécuter l’installation Composer verrouillée ; ne jamais y copier .env, sessions ou logs.
- Faire pointer le DocumentRoot Office vers current/public (une fois le changement Apache approuvé), faire une bascule atomique du symlink current vers la release, puis exécuter apachectl configtest. Recharger uniquement apache2.service si la configuration Apache a changé ; ne toucher à aucun service FPM ni à un autre vhost.
- Depuis des requêtes HTTP effectives, vérifier PHP 8.4 au moyen d’une sonde temporaire restreinte au serveur, sans phpinfo public, puis retirer immédiatement la sonde ; vérifier aussi HTTP/login OIDC existant, assets CSS/JS, liste facture, GET historique et logs sans secrets. Ne faire aucun POST/PATCH.
- Comparer l’état des vhosts API, SpotCard, MenuFlow et autres aux checks de référence. Toute erreur de bootstrap, session, PDF, assets ou lecture facture entraîne rollback immédiat du pointeur/config Office et arrêt ; ne pas toucher aux données.
- Vérifier après bascule que OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED reste false et que les droits/scope OIDC n’ont pas changé. Les valeurs effectives de processus doivent être contrôlées sans exposer de secret.

### Rollback rapide

1. Basculer current vers la release Office précédente ou restaurer le DocumentRoot Office sauvegardé.
2. Si et seulement si la configuration Apache avait été changée, lancer apachectl configtest puis recharger uniquement apache2.service.
3. Vérifier login, assets et accès lecture Office ; comparer les autres vhosts à leurs checks de référence.
4. Ne pas recharger/redémarrer php8.0-fpm ou php8.4-fpm, ne pas restaurer de base, ne pas supprimer de brouillons/traces. Le rollback du code ne modifie aucune donnée facture.

## Changements et invariants

Le seul fichier de rapport ajouté dans le clone de travail Office est celui-ci. Aucun fichier applicatif du dépôt Office n’a été changé. Aucun fichier source/API/shared déployé n’a été écrasé. Le clone shared a été restauré à son état Git propre après le test (cache PHPUnit généré retiré). L’artefact et les clones de préparation sont hors DocumentRoot.

Aucune émission, numérotation, création/modification/suppression de facture, migration, restauration, activation de flags, modification de scope OAuth/OIDC, changement Apache/FPM, reload, redémarrage ou déploiement n’a été effectué.

Les dépendances avant toute opération de facture réelle restent : intégration revue dans Office/main, contrôle HTTP après déploiement, preuve de restauration isolée de la base Office-Api, garde draft-only tenant/principal, audit des effets externes, et autorisation spécifique de recette mutatrice. Ce rapport n’accorde qu’un avis technique sur la reproductibilité PHP 8.4 du candidat.

