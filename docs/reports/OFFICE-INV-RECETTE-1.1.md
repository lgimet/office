# OFFICE-INV-RECETTE-1.1 — préflight et recette contrôlée

**Date du contrôle :** 21 novembre 2026 (heure de Paris)  
**Décision : BLOCKED — aucun GO d’écriture n’est accordé.**  
**Périmètre réalisé :** inspection en lecture seule, tests locaux/mock, syntax checks et vérification des preuves de sauvegarde accessibles. Aucun appel HTTP à l’API de production, aucune connexion de contrôle à la base, aucun changement de configuration ou scope, aucune migration, aucun POST/PATCH/issue/delete et aucune création de fixture n’ont été exécutés.

La déclaration de l’exploitant selon laquelle les factures Office actuelles sont des données de test est consignée comme contexte, mais n’a pas été utilisée comme preuve d’isolation ni de séparation des effets externes.

## Résumé du point d’arrêt

La sauvegarde quotidienne est configurée et le dernier service a réussi le 21 novembre à 03:20 (heure de Paris). Le journal prouve une restauration hors production, réseau désactivé, avec contrôle de cohérence. Cependant, l’identité de la base réellement servie par Office-Api et sa correspondance avec la base restaurée n’ont pas été confirmées.

Le contrôle d’exposition échoue : api.devsys.fr est un vhost parmi plusieurs API hébergées sur le même serveur, la garde d’écriture API repose sur la source devsys et l’état effectif des flags des processus n’est pas vérifié. Aucun garde limitant les écritures au tenant/utilisateur fictif autorisé n’a été démontré. Le tenant de test, les clients/fixtures fictifs, le principal OIDC habilité, les exports/intégrations externes et les releases réellement servies ne sont pas identifiés.

Les versions distantes Git sont consultables, mais les checkouts sur disque ne permettent pas d’établir le code déployé : Office et shared n’ont pas de métadonnées Git ici ; le checkout API est main avec de nombreux changements locaux et fichiers non suivis. Le package shared chargé par Composer est un path/symlink local. Les tests locaux actuels révèlent en outre deux erreurs dans shared.

**Conséquence : aucune activation de flag ni écriture réelle n’est autorisée par ce rapport.** Il n’y a pas de demande de GO immédiat tant que les éléments ci-dessous ne sont pas établis.

## Checklist de préflight

| Contrôle | Résultat | Preuve / écart |
|---|---|---|
| Dépôts et branches distantes | Partiel | Branches distantes lues via GitHub le 21-11-2026 ; SHAs ci-dessous. Aucun fetch local n’a été exécuté. |
| État Git des checkouts | Échec | /home/devsys/office et /home/devsys/shared n’ont pas de métadonnées Git. /home/devsys/api utilise .git-office, branche locale main au SHA acd91744…, mais contient beaucoup de fichiers modifiés et non suivis. Aucun reset/checkout. |
| Code réellement servi / lots de recette | Non établi | Aucun lien probant entre vhosts, répertoire release actif, artefacts Composer et commits distants. |
| Dépendance shared réellement déployée | Non établi | Composer local indique devsys/shared dev-main, path ../shared, référence 2a15ecfa… ; vendor/devsys/shared résout vers le checkout local. Cela ne prouve pas la version du déploiement Office. |
| Hôtes et exposition API | Échec | Apache déclare office.devsys.fr et api.devsys.fr ; api.devsys.fr est hébergé avec d’autres vhosts API (Spotcard, MenuFlow, O-Repas et Taxi, entre autres). Plusieurs PHP-FPM sont actifs. Pools, variables effectives et consommateurs autorisés non établis. |
| Environnements de checkout (pas valeurs effectives des processus) | Partiel | .env Office : APP_ENV=dev, DB_HOST=localhost, DB_NAME=office, DEVSYS_API_BASE_URL=https://api.devsys.fr/api/v1, lecture API true, écriture brouillon false. .env API : APP_ENV=development, APP_URL=https://api.devsys.fr, DB_HOST=localhost ; aucune preuve des variables runtime. Aucune valeur secrète n’est rapportée. |
| Flags côté processus | Non vérifié | Le .env Office local n’est pas une preuve du processus/vhost. Côté API, le .env local n’indique pas les flags ; les défauts du code sont OFF et allowlist vide, mais la configuration runtime est inconnue. |
| Exposition multi-tenant | Échec bloquant | INVOICE_WRITE_SOURCES=devsys est une allowlist de source, pas de tenant. Sur cet hôte partagé, elle peut couvrir d’autres utilisateurs/tenants DevSys disposant des droits. Aucun garde serveur ciblé tenant/utilisateur ni isolation effective n’est prouvé. |
| Sauvegarde quotidienne | PASS avec réserve DB | Timer devsys-backup.timer, 03:20 Europe/Paris. Service réussi le 21-11-2026 de 03:20:02 à 03:21:11, code 0. Journal : 21 bases exportées et contrôles gzip terminés. |
| Intégrité et restauration | PASS générique, DB Office-Api non identifiée | Journal : restauration isolée PASS, réseau désactivé, 51 tables et 45 migrations restaurées/contrôlées dans MariaDB temporaire. La séquence de sauvegarde vérifie une base taxi_api ; elle ne prouve pas que c’est la base servie par Office-Api. |
| Fichiers, destination et rétention backup | Partiel | Script : /home, /etc, /root, /var/spool/cron et dumps DB ; vendor, node_modules et .git exclus. Restic vers OVH déclaré dans le script. Rétention : 7 quotidiens, 4 hebdomadaires, 12 mensuels puis prune. L’inventaire direct des snapshots n’a pas abouti depuis cette session ; journal du service réussi et restauration temporaire disponibles. |
| Base réellement servie / migrations | Non vérifié | Aucun migrate:status ni SQL exécuté : identité exacte de la base API non confirmée. Fichiers 022/023 présents dans l’arbre local API, mais application et tables invoice_source_events, invoice_update_idempotency, invoices.revision non vérifiées sur la cible. |
| Fixtures et effets externes | Non vérifié | Aucun tenant/client/facture fictif identifié côté serveur. Pas de preuve de coupure comptabilité, Pennylane, Stripe, webhooks, e-mail, imports, tâches cron ou rapprochement financier. |
| OAuth/OIDC | Non vérifié | Audience, office-web, scope read:invoices, autorisation write:invoices du seul principal de test et reconnexion OIDC non interrogés. |
| Rollback | Partiel | Valeurs locales connues ci-dessus. Fichier effectif, services PHP-FPM concernés, méthode de reload et vérification runtime restent à identifier avant une fenêtre de test. |
| Tests et syntaxe | Partiel / échec shared | Voir les résultats locaux ci-dessous. Aucun DSN de test MySQL fourni ; il a été explicitement retiré des commandes. |

### Sauvegarde : éléments opérationnels consultés

Commande du service : /usr/local/sbin/devsys-backup.sh ; timer à 03:20 Europe/Paris avec rattrapage persistant. Dernier succès vérifié : 21 novembre 2026, code 0 à 03:21:11.

Le script exporte les bases applicatives individuellement, contrôle chaque archive gzip, sauvegarde les répertoires système et lance une restauration de vérification dans une instance MariaDB temporaire avec networking désactivé. La preuve du dernier run indique 51 tables et 45 migrations. Cette réussite réduit le risque de sauvegarde, mais ne remplace pas la vérification de la base exacte d’Office-Api ni d’une restauration de cette base précise. Aucune restauration n’a été lancée par cette recette.

## Branches et versions source

Branches distantes interrogées en lecture seule. Les SHA sont ceux retournés par GitHub à la date du contrôle ; ils ne prouvent pas un déploiement.

| Dépôt | main distant | Branche fonctionnelle distante | Comparaison |
|---|---|---|---|
| lgimet/office | 0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e | feature/office-ui-inv-1.1 — 26bef297f414ac10fa9b76475d92cd8c1307ecd4 | Fonctionnelle en avance de 5 commits. Code UI/API brouillon et rapport OFFICE-UI-INV-2.1 dans cette branche, pas dans main. |
| lgimet/shared | 83e5b7da32ae20039218586e18dc93ee9ad95852 | feature/office-shared-inv-2-1 — 0b535cb28de2393d38c25d766967514b96d22b3e | main a avancé d’un commit au-delà de la branche fonctionnelle. |
| lgimet/Office-Api | c976b6a8e3722aca63fcbe3c73e6e6ea836584cf | Dernière branche fonctionnelle listée : feature/office-api-inv-3 — 7b0bd864a7f10b1b0c82272dc246d5f0e8c4eff9 | main a avancé de 4 commits au-delà. main contient les migrations 022/023 ; application DB inconnue. |

L’ancien rapport local cite des SHAs Office/shared antérieurs et un checkout API de fonctionnalité. Ils ne sont pas repris comme état courant. Les noms de lots OFFICE-UI-INV-2.1, OFFICE-SHARED-INV-2.1, OFFICE-API-INV-4.1 et INV-4 ne sont pas traités comme tags de déploiement : aucune preuve de release correspondante n’a été trouvée.

### Différences d’intégration à traiter

1. Office main est derrière feature/office-ui-inv-1.1 de 5 commits. Ne pas déployer silencieusement ; revoir le diff et choisir une intégration explicite (merge/PR), puis produire un artefact et noter son SHA.
2. Shared main comprend la branche fonctionnelle plus un commit ; vérifier que l’artefact Composer choisi contient InvoiceOrigin et clientUuid. Le path/symlink local ne suffit pas.
3. Office-Api main contient le travail API merged selon les références distantes. Confirmer le SHA réellement livré, la configuration runtime et le statut des migrations sur la base ciblée avant tout test.
4. Reconstituer le mapping vhost → release → version PHP-FPM → fichier d’environnement → package Composer, sans changer les releases pendant ce préflight.

## Vérifications locales exécutées

Toutes les commandes de test ont explicitement désactivé INVOICE_MYSQL_TEST_DSN et INVOICE_MYSQL_TEST_CONFIRM. Aucun test d’intégration ne s’est connecté à la base de production.

| Dépôt | Vérification | Résultat |
|---|---|---|
| Office | composer test | PASS — 134 tests, 345 assertions. Tests locaux/mocks seulement. |
| Office | Syntaxe PHP (src, tests) | PASS — aucun fichier en erreur. |
| Office | npm run build | NOT_RUN — checkout sans métadonnées Git et build écrivant les CSS compilés dans l’arbre existant ; aucun état initial fiable ne permettait d’écarter une modification locale à écraser. |
| shared | PHPUnit avec autoloader Office | FAIL — 44 tests, 172 assertions, 2 erreurs : legal_type n’est pas un objet dans les fixtures ClientsApiTest (ClientListItem et ClientDetails). |
| shared | Syntaxe PHP (src, tests) | PASS — aucun fichier en erreur. |
| Office-Api | composer test | PASS local avec réserve — 192 tests, 631 assertions, 6 dépréciations PHPUnit. Ce résultat diffère des 226 tests/779 assertions antérieurs et ne valide pas la base cible. |
| Office-Api | Syntaxe PHP (src, tests, bin) | PASS — aucun fichier en erreur. |
| Office-Api | git diff --check sur fichiers suivis | PASS. Fichiers non suivis non couverts par cette commande. |
| Tous | Tests MySQL d’intégration | NOT_RUN — DSN et confirmation absents ; aucune base de production n’a servi de DSN. |

Ces vérifications ne couvrent ni OAuth/OIDC réel, ni routage vhost/release, ni persistance API, ni sécurité inter-tenant, ni comportement InnoDB sur la base servie.

## Matrice E2E T01–T19

Aucun scénario E2E n’a été exécuté. BLOCKED signale les contrôles nécessitant une cible et un principal sûrs ; NOT_RUN est utilisé lorsqu’aucune fixture source autorisée n’est identifiée. Aucun impact persistant de recette : aucune facture/brouillon n’a été créé ou modifié par cette session.

| ID | Statut | Méthode / preuve non sensible | Impact persistant |
|---|---|---|---|
| T01 | BLOCKED | Pas de GET liste : API configurée vers l’hôte réel partagé et aucun tenant de test autorisé. | Aucun. |
| T02 | BLOCKED | Pas de GET historique ; snapshot et provenance non consultés sur la base cible. | Aucun. |
| T03 | BLOCKED | Pas de POST brouillon ; tenant/client fictifs et contrôle d’exposition source non établis. | Aucun. |
| T04 | BLOCKED | Pas de GET détail ; client.uuid, origin, revision et ETag runtime non vérifiés. | Aucun. |
| T05 | BLOCKED | Pas de PATCH ; release compatible et fixture dédiée non établies. | Aucun. |
| T06 | BLOCKED | Pas de calcul/persistance de valeurs via l’API réelle ; aucune donnée soumise. | Aucun. |
| T07 | BLOCKED | Pas de double envoi ou concurrence sur serveur actif ; test MySQL isolé indisponible. | Aucun. |
| T08 | BLOCKED | Pas de simulation de réponse perdue sur vrai POST ; aucune clé de test créée. | Aucun. |
| T09 | BLOCKED | Pas de PATCH/rejeu réel ni confirmation GET ; migrations/idempotence non vérifiées sur DB cible. | Aucun. |
| T10 | BLOCKED | Pas de conflit 409 soumis à l’API ; cible serveur non autorisée. | Aucun. |
| T11 | BLOCKED | Pas de sessions concurrentes ; tenant de test et garde serveur non établis. | Aucun. |
| T12 | BLOCKED | Pas d’accès SQL/fixture historique et aucun test du comportement serveur de secours. | Aucun. |
| T13 | NOT_RUN | Aucune fixture Spotcard/StationID sûre et explicitement autorisée ; aucune fabriquée. | Aucun. |
| T14 | BLOCKED | Pas de POST forgé contre serveur ; mocks locaux ne prouvent pas le comportement déployé. | Aucun. |
| T15 | BLOCKED | Aucun test tenant forgé ; identifiants autorisés et isolation serveur non établis. | Aucun. |
| T16 | BLOCKED | Aucune requête réseau réelle pour 401/403/404/409/412/428 ; simuler jusqu’à autorisation et cible contrôlée. | Aucun. |
| T17 | BLOCKED | Aucune tentative réelle d’émission ou suppression, même négative ; aucune route/UI déployée touchée. | Aucun. |
| T18 | BLOCKED | Aucun brouillon API créé puis flag désactivé ; flags runtime non vérifiés. | Aucun. |
| T19 | BLOCKED | Aucun changement de flag/scope, donc pas de cycle activation/retour ni vérification des flux Office. | Aucun. |

**Émission définitive, annulation, suppression, purge, restauration de base ou mutation SQL : aucune effectuée.**

## Décision et informations requises pour lever BLOCKED

**Décision finale : BLOCKED.** Aucun résultat ne vaut GO_FOR_DRAFT_WRITES_ONLY ; aucune autorisation d’émission ou de mise en production générale n’est accordée.

Avant de redemander le GO opérateur, fournir les éléments manquants suivants :

1. **Cible et code** : mapping vhost Office/API vers releases actives, SHA exacts servis, version PHP-FPM et méthode Composer ; résoudre les écarts Git et faire approuver toute intégration Office depuis sa branche fonctionnelle.
2. **Base** : identité de la DB réellement utilisée par Office-Api, résultat en lecture seule de migrate:status après confirmation de cette connexion, présence des tables/colonnes requises, plus preuve de restauration isolée de cette DB précise. Ne jamais lancer migrate automatiquement sur l’hôte actif.
3. **Garde d’écriture** : démontrer un contrôle serveur pour le tenant/utilisateur fictif avant INVOICE_WRITES_ENABLED=true. La seule allowlist INVOICE_WRITE_SOURCES=devsys est trop large dans ce contexte partagé. Si aucun garde ciblé n’existe, préparer un garde temporaire serveur, le tester hors production et faire revoir son diff avant usage. Le tenant envoyé par le navigateur ne peut pas faire autorité.
4. **Fixtures et intégrations** : identifiants internes d’un tenant et de clients fictifs autorisés, période, vérification qu’aucun export comptable/Pennylane/Stripe, webhook, e-mail, cron d’émission ou rapprochement financier ne consomme les écritures.
5. **OAuth et processus** : confirmer office-web, audience, scope lecture, habilitation write:invoices au seul principal opérateur, reconnexion OIDC, fichier/gestionnaire des variables effectives, unités à recharger et procédure de constat sans révéler de secrets.
6. **Tests** : corriger les deux erreurs legal_type de shared, exécuter le build Office dans un environnement préservant les sorties locales, et exécuter les tests MySQL uniquement sur une copie isolée de la DB, jamais avec un DSN de production.
7. **Autorisation opérateur** : après réception d’un mini-rapport de préflight satisfaisant, l’opérateur doit approuver explicitement le tenant fictif identifié côté serveur, les fixtures/client(s), période, hôtes, écritures autorisées et fenêtre.

## Procédure de retour arrière conditionnelle

Aucun changement n’a été appliqué pendant ce contrôle. Si une fenêtre autorisée est ouverte ultérieurement, enregistrer d’abord les valeurs runtime et les unités/fichiers exacts sans copier de secrets dans le rapport. À la fin ou au premier incident, remettre au minimum les variables suivantes à OFF, retirer le scope temporaire au seul principal de test, puis recharger les processus identifiés :

~~~dotenv
# Office — retour à l’état sans écriture
OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false

# Office-Api — retour à l’état sans écriture
INVOICE_WRITES_ENABLED=false
INVOICE_WRITE_SOURCES=
~~~

Conserver OFFICE_INVOICE_READ_API_ENABLED à sa valeur de départ connue/effectivement vérifiée. Les fichiers et unités exacts doivent être confirmés avant d’exécuter ces commandes modèles :

~~~sh
sudoedit <fichier-environnement-Office-identifie>
sudoedit <fichier-environnement-Office-Api-identifie>
sudo systemctl restart <unite-PHP-FPM-Office-confirmee>
sudo systemctl restart <unite-PHP-FPM-API-confirmee>
sudo systemctl reload apache2
sudo systemctl show <unite-PHP-FPM-Office-confirmee> -p ActiveState -p Result
sudo systemctl show <unite-PHP-FPM-API-confirmee> -p ActiveState -p Result
~~~

Après reload, vérifier les valeurs des quatre flags par un contrôle serveur qui n’imprime que leurs noms/états, confirmer que le scope est retiré au principal de test, puis vérifier que lecture liste/historique restent disponibles. Ne pas restaurer une sauvegarde sur une base active pour corriger un résultat de recette ; toute restauration productive exige une décision opérateur tenant compte des écritures postérieures au snapshot.

Les futurs IDs techniques des brouillons test devront être inscrits ici après une recette autorisée, sans nom, e-mail, adresse ou autre donnée personnelle. Aucun ID de brouillon de recette n’existe à ce stade.
