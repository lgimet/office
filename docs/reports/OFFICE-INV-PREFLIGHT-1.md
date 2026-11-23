# OFFICE-INV-PREFLIGHT-1 — fiabilisation avant recette de brouillons

**Date :** 21 novembre 2026 (Europe/Paris)  
**Décision : NO_GO** pour une recette d’écriture sur les hôtes actuels dans leur configuration/code actuellement servis.  
**Portée :** inspection Git/runtime, contrôles de métadonnées SQL en transaction READ ONLY, lecture/validation du snapshot Restic, inspection statique et tests locaux en copies temporaires.  
**Aucune écriture de facture, aucun flag ou scope activé, aucune migration, aucun restart/reload, aucun déploiement, aucune émission/suppression/annulation et aucune restauration n’ont été effectués.**

Le NO_GO résulte de deux défauts connus : le vhost Office utilise PHP-FPM 8.0 tandis que le SDK shared réellement chargé exige PHP 8.2 et ne se parse pas en PHP 8.0 ; le gate d’écriture global de l’API protège aussi la route d’émission, sans garde brouillon limité au tenant/principal. Des inconnues supplémentaires restent à lever avant qu’un opérateur puisse envisager une future recette.

## 1. Décision synthétique P1–P6

| Domaine | Statut | Résultat et limite |
|---|---|---|
| P1 — vhosts et arbre servi | PASS / FAIL | Apache sert directement les arbres Office/API, sans release versionnée. Empreintes mesurées, mais Office/shared n’ont pas de Git local et le checkout API est très divergent. Le code servi n’est pas attribuable aux SHAs GitHub. |
| P1 — compatibilité Office → shared | FAIL | Office est routé vers PHP 8.0. Le package Composer chargé requiert PHP ^8.2 ; PHP 8.0 échoue à parser InvoiceOrigin, InvoiceDetails et InvoicesApi. |
| P1 — API runtime | PARTIEL | Vhost API vers PHP-FPM 8.4 et racine locale identifiés. L’arbre servi contient 47 fichiers suivis modifiés, 10 supprimés et 79 non suivis ; son SHA Git est l’ancien acd91744…, alors que GitHub main est c976b6a8…. |
| P2 — base et migrations | PASS pour schéma / UNKNOWN pour fixture | La connexion API cible le schéma central office sur MariaDB local. Migrations 022/023, tables, colonne revision, index et clés étrangères présents. Aucun tenant/client fictif n’a été désigné ou vérifié. |
| P3 — snapshot et archive | PASS pour snapshot/dump / UNKNOWN pour restauration Office-Api | Snapshot Restic du 21-11-2026 présent ; il contient office.sql.gz (56 432 octets compressés), dont le flux gzip passe. La restauration automatique existante teste taxi_api, pas office. |
| P4 — garde d’écriture recette | FAIL | Le gate actuel n’est limité ni à un tenant ni à un principal et couvre aussi invoice.issue. Aucun mode brouillon fail-closed dédié n’existe dans le code main consulté. |
| P5 — scope et effets externes | UNKNOWN / FAIL | office-web est autorisé à demander write:invoices dans l’API, mais Office ne le demande pas actuellement. L’habilitation du principal de test est inconnue. Pas de trigger/event DB facture observé ; l’absence d’effets externes pour tous les consommateurs n’est pas démontrée. |
| P6 — tests/package | FAIL / PARTIEL | Office passe ; le checkout shared local échoue sur deux fixtures obsolètes ; API passe sur son arbre local. Build npm non exécuté faute de dépendance disponible hors ligne. Le PHP 8.0 du vhost échoue sur le SDK. |

## 2. P1 — runtime, empreintes et versions

### Vhosts et chaîne observée

Les vhosts office.devsys.fr et api.devsys.fr servent directement les racines applicatives se terminant par .../office/public et .../api/public. Aucun symlink current/release daté n’est utilisé par ces vhosts. Les répertoires de releases devsys-web trouvés sur la machine sont distincts et ne prouvent pas une release Office ou Office-Api.

| Hôte | DocumentRoot logique | PHP-FPM | Entrée / configuration | Empreinte SHA-256 de src, config, public |
|---|---|---|---|---|
| office.devsys.fr | .../office/public, racine de code office | php8.0-fpm.service | public/index.php charge vendor/autoload.php, puis le .env Office | bb96ba1504d6bfd138c77382a2c0b541f5e917181164616cb8b2d11361093e9f (191 fichiers) |
| api.devsys.fr | .../api/public, racine de code api | php8.4-fpm.service via le handler Apache global | public/index.php charge vendor/autoload.php, puis Dotenv::safeLoad() | c687365cc30a626371f85bf15a19f676458a4379e399eb4dcf7b6b56c1257f75 (212 fichiers) |

SHA-256 des points d’entrée : Office a77c5875b2f12c03eb23e0187dd1e7b105acfed420ad9aa35511abf27c0a9f81 ; API 1ff0a99e292fedd8cd17813a9eb85f65cb8b122d980274f849cea0fee25583fd. Ces empreintes identifient le contenu sur disque ; ce ne sont ni des SHA de commit ni une attestation signée de release.

Unités observées sans les redémarrer : php8.0-fpm.service pour Office, php8.4-fpm.service pour l’API, apache2.service pour les vhosts. Aucun restart/reload n’a eu lieu. Les variables runtime de workers n’ont pas pu être attestées directement ; les sources Apache/FPM examinées ne définissent pas de surcharge DB/flags ciblée, mais cela ne remplace pas une inspection effective des workers pendant une future fenêtre.

### Flags avant, pendant et après ce contrôle

| Variable | Avant (valeur checkout) | Pendant | Après |
|---|---|---|---|
| OFFICE_INVOICE_READ_API_ENABLED | true dans .env Office | inchangé | inchangé |
| OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED | false dans .env Office | inchangé | false dans le checkout |
| INVOICE_WRITES_ENABLED | absent du .env API ; défaut du bootstrap false | inchangé | absent/défaut false dans le checkout |
| INVOICE_WRITE_SOURCES | absent du .env API ; défaut du bootstrap liste vide | inchangé | absent/défaut liste vide dans le checkout |

Les workers PHP actifs n’ont pas livré de snapshot d’environnement vérifiable ; ces valeurs démontrent le checkout et les défauts applicatifs, pas une attestation absolue de chaque worker. Aucun changement de valeur n’a eu lieu dans ce contrôle. Les scopes effectifs d’une session utilisateur ne sont pas déduits des scopes autorisés du client OAuth.

### Branches GitHub et limites des checkouts

Les refs distantes ont été interrogées en lecture seule. La branche fonctionnelle Office a changé pendant ce contrôle : son SHA a été relu avant la comparaison finale.

| Dépôt | GitHub main courant | Branche fonctionnelle courante | Écart observé |
|---|---|---|---|
| lgimet/office | 0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e | feature/office-ui-inv-1.1 — 581095584f2f0db22f97d9a19eb7e3403f7e3db2 | Feature en avance de 6 commits ; elle contient le code UI/API brouillon UI-INV-2.1, absent de main. |
| lgimet/shared | 83e5b7da32ae20039218586e18dc93ee9ad95852 | feature/office-shared-inv-2-1 — 0b535cb28de2393d38c25d766967514b96d22b3e | Feature derrière main d’un commit. |
| lgimet/Office-Api | c976b6a8e3722aca63fcbe3c73e6e6ea836584cf | feature/office-api-inv-3 — 7b0bd864a7f10b1b0c82272dc246d5f0e8c4eff9 | Feature derrière main de quatre commits. |

Aucun fetch local n’a été effectué : Office/shared n’ont pas de métadonnées Git locales ; le checkout API est très modifié et n’est pas sûr à mettre à jour.

- Office local : aucun .git, branche/HEAD/diff impossibles à prouver. L’arbre servi contient les contrôleurs/services draft de la feature, mais aucun SHA Git local ne les identifie.
- shared local : aucun .git. Le package chargé par l’autoloader Office est devsys/shared dev-main, résolution Composer path vers ../shared, référence installée 2a15ecfa6794e090ec6ea941a2ecc62b3e139c12, symlink vers l’arbre shared local. Ce n’est pas la preuve d’un artefact shared/main (83e…).
- Office-Api local : .git-office, branche main, HEAD et ref locale origin/main égaux à acd91744b35fc9a1963bba5c283a4c5dc0197f92. État local : 47 modifiés, 10 supprimés, 79 non suivis. La racine sert directement ces fichiers ; des fichiers invoice/migrations importants sont non suivis. GitHub main est c976…, donc le checkout local n’est ni propre ni aligné avec main.

Composer local est 2.10.2 ; PHP CLI Composer est 8.4.24. Office a un composer.lock local, mais .gitignore l’ignore ; sa dépendance path utilise symlink: true. Composer résout depuis l’autoloader Office les classes chargées : InvoiceOrigin et InvoiceDetails (namespace DTO), les propriétés InvoiceDetails.clientUuid/clientUuidProvided/origin, et les méthodes InvoicesApi.get/createDraft/updateDraft. Cela confirme le mapping de classes local, pas leur compatibilité avec le PHP-FPM de l’hôte.

### Incompatibilité runtime confirmée

Le vhost Office utilise PHP 8.0. Le package shared local déclare php: ^8.2 ; InvoiceOrigin et InvoiceDetails déclarent final readonly class. php8.0 -l échoue sur ces deux fichiers et sur InvoicesApi (syntaxe de propriété promue typée incompatible). Les tests Office effectués sous PHP CLI 8.4 ne couvrent pas ce runtime Office 8.0.

**NO_GO technique immédiat :** ne pas activer une écriture brouillon sur Office avec ce routage PHP. Corriger par une release contrôlée Office sur PHP 8.2+ (version disponible 8.4 après tests) et vérifier le handler, l’autoloader, les classes et les empreintes. Ne pas modifier le vhost pendant ce lot.

### Plan d’intégration sans exécution

1. Revoir le diff office/main → feature/office-ui-inv-1.1 (6 commits) et choisir un PR/merge explicite ; aucun déploiement automatique.
2. Créer une release Office immuable compatible PHP 8.2+, idéalement avec la version disponible 8.4 après tests. Installer shared depuis un artefact/révision contrôlé et enregistrer SHA package, composer.lock et empreintes.
3. Garder le path symlink pour le développement si nécessaire, mais ne pas l’utiliser comme preuve de production : pour une release, préférer un package VCS/tag ou un artefact copié/pinné ; vérifier que vendor/devsys/shared résout dans le répertoire de release et correspond au SHA approuvé.
4. Reconstituer un checkout propre Office-Api depuis le SHA GitHub main c976…, en préservant le checkout courant ; comparer l’arbre servi avec main, puis préparer une revue et une release distinctes.
5. Après une mise en place autorisée, consigner les SHAs/empreintes et la version PHP des workers. Aucun changement n’a été réalisé ici.

## 3. P2 — base et migrations (lecture seule)

Le point d’entrée API charge son .env, puis config/database.php construit la connexion centrale avec DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME et DB_PASSWORD. Le checkout servi indique DB_HOST=localhost, DB_DATABASE=office. Les repositories invoice et InvoiceCommandService utilisent cette connexion centrale et filtrent les factures à partir du TenantId/effective_tenant_id serveur. Aucun routage vers une base dédiée n’est utilisé dans les repositories facture examinés.

Après confirmation de cette cible par le chemin de configuration API, des contrôles SELECT seulement ont été exécutés dans une transaction MariaDB READ ONLY, puis annulés :

- Schéma office ; MariaDB 10.9.4.
- Tables invoices, clients, tenants, migrations, invoice_source_events et invoice_update_idempotency présentes.
- invoices.revision et invoices.tenant_id présents.
- migrations contient 022_add_invoice_source_idempotency.sql et 023_add_invoice_draft_revisions_and_update_idempotency.sql.
- Index uniques attendus présents : uq_invoices_tenant_id, uq_invoice_source_event, uq_invoice_create_idempotency, uq_invoice_update_idempotency.
- Contraintes FK tenant/facture présentes sur les tables d’événement et d’idempotence.
- Zéro trigger sur factures/lignes/événements d’idempotence ; zéro événement DB dont le nom correspond à facture/export.

Aucune ligne de client, facture ou tenant n’a été lue. Aucun tenant/client fictif n’a été désigné par l’opérateur ; son appartenance au périmètre test n’est donc pas certifiée.

**migrate:status n’a pas été lancé.** Le code de MigrationCommand exécute d’abord CREATE TABLE IF NOT EXISTS migrations même pour migrate:status ; la commande n’est donc pas strictement non mutatrice. Les métadonnées ci-dessus et les noms de migration de la table ont servi d’équivalent read-only. Aucun migrate n’a été exécuté.

## 4. P3 — devsys-backup et restauration propre à Office-Api

- Timer quotidien à 03:20, Europe/Paris.
- Dernier run contrôlé le 21-11-2026 : début 03:20:02, fin/succès 03:21:10/11, code 0.
- Journal : 21 bases applicatives exportées, dont office ; contrôles gzip des exports terminés.
- Snapshot Restic le plus récent : d0257e8cba2730eb2dff44b2a2dca33cf433a217319ee1e3dbfab7895d50b13c, daté 2026-11-21T03:20:11+01:00 ; 30 snapshots quotidiens listés.
- Le snapshot contient une entrée office.sql.gz ; flux Restic vers gzip -t : PASS, taille comprimée 56 432 octets. Aucun fichier n’a été extrait sur disque.
- Rétention : 7 quotidiens, 4 hebdomadaires, 12 mensuels, puis prune. Timer actif, dernier succès de rétention observé le 15-11-2026 ; prochaine échéance le 22-11-2026.
- La restauration automatique existante lance une MariaDB temporaire networking désactivé et valide taxi_api (51 tables, 45 migrations). Cela ne démontre pas une restauration de office ; office.sql.gz n’a pas été restauré.

**Restauration Office spécifique : UNKNOWN et non exécutée.** Après autorisation opérateur séparée, préparer une VM/instance temporaire dédiée, sans routage réseau ni credential de production, avec datadir/socket uniques ; vérifier @@skip_networking=1, @@socket et @@datadir avant import. Exemple à adapter après identification du chemin archive via Restic :

~~~sh
restic dump <snapshot-office> <chemin-archive-office.sql.gz> \
  | gzip -dc \
  | mariadb --no-defaults --protocol=socket --socket=<socket-mariadb-temporaire> -uroot
~~~

Contrôler ensuite uniquement information_schema, tables et migrations 022/023 dans l’instance isolée, puis arrêter/supprimer cette instance temporaire seulement après preuve. Cette procédure n’a pas été exécutée.

Pour rollback d’une future recette, privilégier l’arrêt par flags et le retrait de la demande de scope de la session test. Ne pas restaurer aveuglément le snapshot en production : cela pourrait écraser les écritures postérieures et affecter d’autres applications utilisant office.

## 5. P4 — garde actuel et proposition fail-closed

### Ordre actuel

Sur l’API main consultée, pour les routes protégées :

1. Middlewares globaux (request id, sécurité, CORS, JSON, journalisation).
2. AccessTokenMiddleware.
3. RequireScopeMiddleware : read:invoices pour GET ; write:invoices pour POST/PATCH/issue.
4. RequireInvoiceAccessMiddleware :
   - principal utilisateur : validation membership via RequireTenantContextMiddleware, effective_tenant_id depuis le principal, invoice_source_app=devsys ;
   - client OAuth : résolution tenant/source depuis oauth_client_invoice_access.
5. InvoiceWritesEnabledMiddleware pour create, update et issue : exige INVOICE_WRITES_ENABLED et une source serveur présente dans INVOICE_WRITE_SOURCES.
6. Contrôleur : refuse tenant_id/tenant_uuid forgés et source_app différente de la source serveur ; les repositories relient l’ID facture au tenant effectif.

Aucun tenant/source/identity fourni par le navigateur ne doit faire autorité. Les contrôles API utilisent le contexte authentifié.

### Défaut établi

Le gate actuel n’identifie ni le tenant fictif, ni le sujet OIDC opérateur. Si INVOICE_WRITES_ENABLED=true et INVOICE_WRITE_SOURCES=devsys, les utilisateurs DevSys ayant write:invoices et un tenant actif passent le même gate. La même ouverture autorise POST /issue, car issue exige le même scope et traverse la même allowlist. Activer les variables existantes seules n’est donc pas sûr pour une recette draft-only.

### Proposition minimale pour une branche API propre (non réalisée)

Ajouter un middleware de mode recette brouillon, désactivé par défaut, par exemple INVOICE_DRAFT_RECETTE_GUARD_ENABLED=false, avec configuration serveur obligatoire du tenant UUID attendu et du sujet OIDC autorisé. Valider ces identités uniquement via AuthenticatedPrincipal, effective_tenant_id issu de la membership et source déjà résolue côté serveur ; exiger le type principal utilisateur attendu. Refuser si une configuration manque ou est invalide.

Attacher ce garde aux handlers create et update ; lorsque le mode recette est actif, refuser invoice.issue avant le contrôleur, sans affecter les GET. Maintenir le gate global et son contrôle de source après le garde. Flag OFF, principal absent/mauvais type, sujet étranger, tenant étranger, source inattendue ou paramètre tenant forgé ⇒ aucune mutation. Ne pas faire confiance au body/header/query tenant.

Ordre futur : authentification → scope → contexte tenant/source serveur → garde recette tenant+principal et blocage issue → gate écriture/source → validation contrôleur → commande SQL.

Tests minimaux sur la nouvelle branche : tenant autorisé/étranger ; sujet autorisé/étranger ; source erronée ; flag OFF ; principal absent/mauvais type ; tenant_id/tenant_uuid navigateur forgé ; source_app forgée ; /issue refusé en mode draft-only ; GET liste/détail inchangé ; aucun fallback SQL Office. Tester mocks puis copie MySQL isolée.

Aucun patch n’a été créé. Le checkout API local est déjà très modifié et inclut justement le middleware/migrations non suivis ; une branche/worktree fiable basée sur main distant n’est pas disponible dans ce checkout. Écrire maintenant risquerait d’inclure ou d’écraser l’état préexistant. Préparer la correction dans un checkout propre dédié Office-Api depuis le SHA main c976…, la faire revoir et tester, sans déploiement dans ce lot.

## 6. P5 — scopes, principal et effets externes

### OAuth/OIDC

Office utilise le client OIDC office-web et OfficeAccessTokenProvider côté serveur : il transmet l’access token de la session utilisateur et le rafraîchit via OIDC. Aucun token n’a été lu ou consigné.

Contrôles non sensibles :

- Configuration Office actuelle : openid profile email read:clients write:clients delete:clients read:invoices ; elle ne demande pas write:invoices.
- Le client API office-web est actif/confidentiel et sa liste allowed_scopes contient read:invoices et write:invoices.
- Aucune ligne de mapping office-web dans oauth_client_invoice_access. L’accès Office suit le chemin principal utilisateur, où le tenant vient du principal/membership ; aucun tenant test n’a été sélectionné.
- Consentement effectif et scope présent dans la session opérateur test : UNKNOWN, aucun principal test n’étant identifié.

Aucun scope n’a été modifié. Pour une future recette, l’opérateur devra faire demander le scope au seul principal autorisé puis renouveler sa session OIDC. La configuration de scopes Office est globale : faire cette demande seulement après la mise en place du garde tenant/principal API, sinon d’autres utilisateurs pourraient obtenir le scope demandé.

### Effets tiers et fixtures

Les méthodes de création/modification brouillon inspectées appellent le stockage API ; aucune intégration Stripe/Pennylane/e-mail/webhook n’est visible dans le chemin de commande draft. Les métadonnées DB ne montrent pas de trigger/event DB facture. Aucun nom de timer/cron facture/export/paiement n’a été trouvé.

Ce contrôle statique ne prouve pas l’absence d’un autre consommateur API, d’un worker externe, d’un export SaaS ou d’une tâche non nommée. La séparation des effets externes reste UNKNOWN. Aucun message, e-mail, webhook, PDF émis, paiement ou export n’a été déclenché.

Fixtures futures : tenant fictif identifié côté serveur, client fictif confirmé dans la même base, source devsys, brouillon sans numéro, données minimales entièrement fictives, sans coordonnées personnelles. Aucune fixture n’a été créée. Fenêtre, tenant et clients doivent être fournis/validés par l’opérateur avant recette.

## 7. P6 — tests et packaging

Les suites ont tourné dans des copies temporaires sous /tmp, sans .env ni DSN MySQL et sans écriture dans les arbres servis. Les copies ont été nettoyées à la fin.

| Dépôt/arbre local | Résultat |
|---|---|
| Office | composer test : PASS, 134 tests, 345 assertions. PHP CLI 8.4 ; ne couvre pas le FPM 8.0 du vhost. |
| shared local chargé via path | FAIL, 44 tests, 172 assertions, 2 erreurs de contrat legal_type dans ClientsApiTest : ClientListItem et ClientDetails exigent un objet. |
| Office-Api local | composer test : PASS, 192 tests, 631 assertions, 6 dépréciations PHPUnit. Aucun test MySQL sauté dans ce checkout, car les 11 méthodes d’intégration MySQL sont absentes de son arbre local. |
| Office npm build | NOT_RUN : npm ci --offline dans la copie temporaire a échoué avant le build ; to-regex-range@5.0.1 est absent du cache npm. Aucun CSS servi n’a été modifié. |
| php -l PHP 8.4 | PASS sur les copies : Office 114 fichiers, shared 34, API 242. |
| php8.0 -l shared chargé | FAIL sur InvoiceOrigin, InvoiceDetails et InvoicesApi, incompatibilité du vhost Office confirmée. |
| git diff --check | PASS sur fichiers suivis du checkout API avant ce rapport ; fichiers non suivis non couverts. Office/shared n’ont pas de .git. |

### Analyse shared legal_type

Le checkout shared local est désynchronisé : ses fixtures ClientsApiTest utilisent l’ancien champ type: company et omettent les champs attendus. Les DTO de ce checkout imposent déjà legal_type sous forme objet et la présence de client_category. Les fixtures de shared/main actuel (SHA 83e5…) ont legal_type: {slug, name} et client_category ; elles correspondent à la validation stricte.

Conclusion : les deux erreurs reproduites concernent les tests du checkout local ancien, pas un défaut démontré du DTO main. Aucun assouplissement de validation ni patch de fixture n’a été appliqué. Exécuter la suite du vrai SHA main dans une copie propre avant d’affirmer qu’elle passe.

### Intégration MySQL sur main distant

Le main distant Office-Api contient tests/Integration/InvoiceCommandMySqlIntegrationTest.php avec 11 méthodes, protégées par un DSN limité à office_api_inv3_test et une confirmation isolated. Sans DSN, elles seraient sautées ; elles ne sont pas présentes dans le checkout local testé, d’où zéro skip plutôt que 11. Aucune n’a été exécutée. Plusieurs méthodes recréent le schéma de test ou exercent issue : les lancer uniquement sur une base jetable explicitement isolée, jamais sur office.

Aucun INVOICE_MYSQL_TEST_DSN de production n’a été défini ; ces tests n’ont pas été exécutés.

## 8. P7 — plan futur OFFICE-INV-RECETTE-1.2

### Prérequis avant demande d’accord opérateur

1. Corriger le runtime Office vers PHP 8.2+ et produire une release immuable ; confirmer le FPM du vhost, les classes SDK chargées, Composer/package/SHA et les empreintes.
2. Réconcilier les sources locales avec les SHAs GitHub ; revue/PR Office feature (6 commits) ; utiliser shared main ou package versionné ; reconstruire Office-Api depuis un checkout propre main.
3. Ajouter/tester dans une branche API dédiée le garde draft-only P4, flag OFF par défaut, tenant+sujet définis serveur ; vérifier que /issue refuse et GET reste inchangé. Ne pas déployer pendant ce lot.
4. Restaurer le dump office dans une MariaDB temporaire dédiée et isolée après autorisation distincte de cette opération.
5. Obtenir du responsable tenant/client fictifs identifiés côté serveur, période, principal OIDC, vérification d’effets externes et liste des consommateurs/services concernés.
6. Valider scope/session opérateur et quatre flags à l’état initial ; produire un mini-rapport puis demander un GO humain explicite couvrant hôtes, tenant, principal, routes create/update, fixtures et fenêtre.

### Ordre d’activation future et services

Uniquement après les prérequis et un GO explicite distinct :

1. Relever valeurs runtime/scopes sans secret et préparer le rollback.
2. Activer le garde draft-only avec tenant UUID et subject exacts ; garder les écritures globales OFF pendant la validation du garde.
3. Vérifier par tests non mutateurs que GET reste disponible, issue/delete/annulation restent refusées et les autres tenants/principaux/sources sont refusés.
4. Ajouter temporairement la demande write:invoices pour la session test via le chemin OIDC approuvé, puis renouveler la session du seul opérateur.
5. Activer la fenêtre create/update après contrôle sur les vrais workers : flag Office et gate API/source. Aucune route issue ne doit s’ouvrir.
6. Recharger uniquement les unités confirmées si le mode de configuration le nécessite : php8.x-fpm.service Office, php8.4-fpm.service API, apache2.service seulement si handler/vhost change. Rien n’a été rechargé dans ce lot.

### Arrêt, rollback et preuve de fin

Arrêt immédiat si tenant/source inattendu, défaut 5xx, conflit de version non compris, fallback SQL, route /issue accessible, effet externe, fuite de données, empreinte/build différent ou état flag incertain.

Rollback par flags au premier incident ou à la fin de fenêtre :

~~~dotenv
OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false
INVOICE_WRITES_ENABLED=false
INVOICE_WRITE_SOURCES=
~~~

Conserver la lecture à son état initial vérifié. Retirer la demande temporaire write:invoices et révoquer uniquement le consentement temporaire du principal test si requis ; ne pas modifier les autorisations globales préexistantes de office-web sans décision séparée. Recharger uniquement les unités préalablement identifiées et vérifier les noms/états de flags runtime sans imprimer de secret. Confirmer liste/historique accessibles, brouillons test persistants, issue/suppression bloquées et aucun export envoyé.

Ne jamais annuler la recette par SQL ni restaurer aveuglément le snapshot de production. Consigner les IDs techniques des brouillons seulement après exécution autorisée, sans données personnelles.

## 9. Conclusion

**Décision : NO_GO** pour ouvrir aujourd’hui les écritures brouillon sur office.devsys.fr / api.devsys.fr.

Motifs bloquants :
- PHP 8.0 du vhost Office incompatible avec le shared chargé (échec de parsing reproductible) ;
- gate API actuel source/global permet aussi issue et ne limite ni tenant ni principal ;
- sources API locales non alignées à GitHub main, Office/shared sans SHA local ;
- fixture tenant/client et consentement du principal non identifiés ;
- restauration de la base office non démontrée, même si le dump du dernier snapshot passe gzip ;
- inhibition des autres consommateurs/effets tiers non démontrée.

Les migrations 022/023 sont appliquées à la base centrale office. Aucun changement de facture, scope, flag, code, migration, déploiement, service ou donnée n’a été effectué pendant ce préflight. Un GO éventuel ne pourra être qu’un GO_FOR_OPERATOR_APPROVAL après correction et preuves ci-dessus, jamais un GO général pour écrire ou émettre des factures.
