# OFFICE-INVOICE-NOTES-1-ACTIVATION

Date : 25 novembre 2026. Verdict : **`READY_AWAITING_GO`**. Préflight terminé en lecture seule. Aucun candidat de release n’a été installé, aucune migration n’a été appliquée et aucune bascule/configuration de production n’a été effectuée.

## Sources et versions constatées

| Dépôt | `origin/main` vérifié | Code notes |
|---|---|---|
| shared | `05ee4fdbd1ae31556a4d3f56bc9792c80831bfaf` | `3da1cabb276e6b0e39a2de2710777cba370b3a49` |
| Office-Api | `34825341bab7ed1a8cc16bc6bf7f04bef2427f73` | `1b977efc3ca0b34733370fdcca0f02fa1baf5d18` |
| office | `7df0921245494c97297c198695fc373af2e762ae` | `3986a8da0804d2960f36aab0cd0d9e9f71a325d1` |

Releases réellement actives, vérifiées par `current`, manifestes et DocumentRoots : API `api-office-mvp-pdf-20261125-a115d5f` (source `a115d5f7d36dd148f9d65296a7bb2151caa34cc1`) ; Office `office-mvp-pdf-20261125-dfaa435` (source `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc`). Les versions précédentes conservées pour retour applicatif sont `api-invoice-facturx2-20261124-7f88c7c-r1` et `office-invoice-facturx2-20261124-eb2f7aa`. Les vhosts API/Office pointent vers leurs releases actives ; les vhosts login restent sur leur DocumentRoot existant et n’ont pas été modifiés. `office/main` verrouille le SDK notes `3da1cabb…` ; ce SHA est disponible dans `shared/main`.

## Préflight

| Contrôle | Résultat | Preuve non sensible |
|---|---|---|
| Mains / working trees | PASS | SHA ci-dessus ; branches de travail propres après `git fetch`. |
| Espace et activité | PASS | `/dev/sdb`, 30 Gio, 15 Gio disponibles (48 % utilisés), inodes 12 % utilisés. `/home/devsys` et `/mnt/devsys-backup-work` sont le même volume et ne sont comptés qu’une fois. Aucun processus de release/build observé. |
| Sauvegarde | PASS | `devsys-backup.service` : résultat `success`, code 0, 25/11 de 03:20:01 à 03:26:23 CET ; timer actif, prochaine échéance 26/11 à 03:20 CET. La preuve opérateur locale indique une restauration isolée vérifiée à 03:26 CET, SHA de preuve `571aaa1e…5608b`. Aucun job de sauvegarde/restauration n’a été relancé ici. |
| Base / migrations | PASS pour le préflight | Connexion en transaction read-only : base `office`, MariaDB 10.9.4. Registre et schéma 027/028 cohérents ; `invoices.document_profile` nullable, `invoice_pdf_archives` présent. Comparaison avec les migrations de `Office-Api/main` : **seule 029 est en attente**. Les six nouvelles colonnes de 029 sont absentes, sans état DDL partiel observé. |
| Migration 029 | PASS intégrité, NOT_RUN application | SHA-256 du fichier source `41eed5b3226a75a218caaeb0dea7c742fcc6fcd0e7c7887ef1ed5002959e8d9f`, conforme. Aucun SQL d’écriture exécuté. Le runner global n’a pas été lancé. |
| Gates | PASS | Configuration persistante : `INVOICE_FACTURX_ENABLED=true`, `INVOICE_WRITES_ENABLED=true`, `INVOICE_WRITE_SOURCES=devsys`, `OFFICE_INVOICE_FACTURX_ENABLED=true`, lecture API et brouillons API Office ON. Aucun override de ces gates dans les environnements des workers PHP-FPM inspectés. Valeurs inchangées. |
| Stockage/runtime privé | PASS | Archives/workdir PDF classiques et Factur-X existent hors webroot, `www-data`, mode `0700`; `www-data` peut y lire/écrire/traverser. Toolchain Factur-X `/opt/devsys/facturx-runtime/1.09.2`, `root:www-data`, `0750`. `OFFICE_STORAGE_DIR=/var/lib/office`, `www-data`, `0750`, accessible au worker. Le chemin `storage` local de la release Office active n’existe pas ; le runtime utilise le chemin persistant configuré ci-dessus. Aucun chemin n’a été créé/modifié. |
| Configuration / services | PASS | `.env` actifs restent liés aux fichiers persistants habituels. `apachectl configtest` : `Syntax OK`; `php-fpm8.4 -t` : succès. GET loopback épinglés : API santé 200, découverte OIDC login 200, racine Office 302 ; GET non authentifié `/api/v1/invoices/issuer-notes` : 401. |
| Candidats / bascule | NOT_RUN | Aucun paquet de release, checksum candidat, dry-run API ou changement de vhost/current n’a été préparé ou exécuté en attente du GO. |
| Recette authentifiée | NOT_RUN | Aucune session Office n’a été utilisée ; aucun GET authentifié des paramètres ni PUT des mentions. Aucun texte juridique n’a été lu ou modifié. |
| Suites de code / build candidat | NOT_RUN dans ce lot | Aucun code source n’a changé depuis l’intégration. Les suites de l’intégration restent disponibles dans `OFFICE-INVOICE-NOTES-1-INTEGRATION.md` ; l’installation depuis les locks et les checks de plateforme seront faits sur les candidates après GO. |

Le helper API-only fusionné accepte le profil actif, revérifie gates/configuration, vhosts et `current` sous verrou, et limite ses écritures aux deux vhosts API, au marqueur `current` API et à ses sauvegardes/verrou sous `api-deploy`. Son implémentation contrôle explicitement 026–028 mais pas 029 : le futur préflight devra vérifier indépendamment le SHA/manifest de 029 et le schéma ci-dessus. Toute application rechargera Apache globalement, donc les vhosts login seront relus même si leurs fichiers ne changent pas.

Pour Office, le rapport de déploiement antérieur `OFFICE-INVOICE-WEB-DEPLOY-1` documente la méthode Office-only déjà utilisée : sauvegarde exacte du vhost Office, remplacement des quatre seules occurrences `DocumentRoot`/`<Directory>` vers la candidate, `apachectl configtest`, mise à jour atomique du marqueur `current`, puis reload gracieux Apache si la configuration change. Le reload est global. Aucun fichier login ni OIDC n’est dans le périmètre.

## GO attendu et opérations non réalisées

Le GO opérateur distinct manque. Pour autoriser la suite, il doit couvrir explicitement les hôtes `api.devsys.fr` et `office.devsys.fr`, la base `office`, l’application de **la seule migration 029** (checksum ci-dessus), les sources API/Office/shared du tableau, le maintien exact des gates actuels, et un reload gracieux Apache global reconnaissant que login sera relu. Les IDs/empreintes de releases candidates seront calculés et présentés après leur construction et avant les bascules. La migration et les bascules API puis Office resteront des étapes séparées avec leurs contrôles et rollback vers les versions précédentes nommées plus haut.

Aucune facture, numérotation, séquence, snapshot ou archive n’a été consulté ou modifié. Aucun changement `.env`, vhost, release active, migration, gate, OIDC ou sauvegarde n’a été effectué. Aucune génération PDF/Factur-X n’a été déclenchée.

**Prochaine étape unique :** obtenir le GO opérateur couvrant l’application de 029 et les deux bascules API → Office avec le reload Apache global explicite ; jusque-là l’état reste `READY_AWAITING_GO`.
