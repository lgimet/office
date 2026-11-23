# OFFICE-DEPLOY-PHP84-2.2 — Préflight de bascule Office en lecture seule

**Date :** 22 novembre 2026 (Europe/Paris)
**Décision :** **DEPLOYED_READ_ONLY_PARTIAL** — le GO opérateur a été reçu et la release a été activée sur Office uniquement. Les contrôles HTTP anonymes et PHP-FPM passent ; OIDC authentifié et lecture d’une facture historique restent non démontrés faute de session opérateur.
**Périmètre effectué :** préflight, lien `current`, modification des quatre chemins du vhost Office, reload Apache seul, sonde loopback brève puis supprimée, GET uniquement. Aucune écriture métier, aucun flag/scope modifié, aucun merge/push.

## Références et arbre de travail

Références distantes relues au début du lot :

| Dépôt | Branche | SHA |
|---|---|---|
| `lgimet/office` | `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` |
| `lgimet/office` | `work/office-deploy-php84-2.1` | `debcf3f70979a5b78fd4bba0847ce4fec703edff` |
| `lgimet/shared` | `main` | `83e5b7da32ae20039218586e18dc93ee9ad95852` |
| `lgimet/Office-Api` | `main` | `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` |

Les trois rapports demandés sont présents sur le checkout Office 2.1 et ont été relus. Office `main` est ancêtre de 2.1 ; la branche 2.1 est 11 commits en avance, 0 en retard au moment du contrôle. Les changements de code de l’artefact viennent de l’ancien commit de feature `470b486…` et du packaging `7f84691…`; les commits suivants sont des rapports. Aucun changement n’a été fait à `shared` ou `Office-Api`.

Le rapport de ce lot est préparé dans le dépôt Office, worktree `/home/devsys/office-deploy/worktrees/office-deploy-php84-2.2`, branche locale dédiée `work/office-deploy-php84-2.2`, issue de la tête distante 2.1. Cette branche n’est pas poussée et aucun PR/merge n’a été créé.

## Candidat vérifié

- Archive : `/home/devsys/office-deploy/artifacts/office-php84-20261122-7f84691-83e5b7d.tar.gz`.
- SHA-256 vérifié : `a50cdca6fd246a2a4a26bf7e7282a1e733d7a3e4c40ce2fcd6966e7f83065c01`.
- Release inactive : `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d`.
- `SHA256SUMS` de la release : 241 fichiers vérifiés, 0 échec. `composer.lock` fixe `devsys/shared` à `83e5b7da32ae20039218586e18dc93ee9ad95852`; `vendor/devsys/shared` est un dossier copié, non un symlink.
- `composer.json` déclare PHP `^8.2`. Archive inspectée : `.htaccess`, `composer.lock`, manifeste et checksums présents ; aucun `.env`, `.git`, `storage`, upload, fichier SQL/dump, clé ou session correspondant aux motifs contrôlés.
- Archive et code sont identiques aux versions testées dans les rapports 2 et 2.1. Les tests antérieurs (`composer test` Office 134 tests/345 assertions, shared 46/197 ; `npm ci`/`npm run build`, audit npm, `git diff --check`) ne sont pas rejoués dans ce préflight. Le présent changement est documentaire seulement.

## État actif observé

| Contrôle | Résultat |
|---|---|
| Vhost Office HTTP/HTTPS | `office.devsys.fr`, DocumentRoot et `<Directory>` vers `/home/devsys/office/public`, `AllowOverride All`, `Require all granted` |
| Handler | Handler Office PHP 8.0 commenté ; handler global configuré vers `/run/php/php8.4-fpm.sock` |
| `current` | Absent ; aucune release candidate n’est visée par le vhost |
| Sauvegarde vhost | Présente ; configuration active identique octet pour octet à la copie sauvegardée du lot 2.0 |
| Configurations | `apachectl configtest` : `Syntax OK`; `php-fpm8.4 -t` : configuration valide |
| Services | Apache et PHP 8.4-FPM actifs. Aucun service n’a été rechargé/redémarré. Le lot 2.1 a démontré la release par Apache isolé→FPM 8.4 (`8.4.24`, `fpm-fcgi`), y compris avec DocumentRoot symlinké. |
| `.env` Office | Hors release, symlink candidat vers `/home/devsys/office/.env`; mode effectif `0640`, owner/group `devsys:www-data`; lisible par `www-data` |
| Cache | Le symlink candidat mène à `/home/devsys/office/var/cache`, mode `0700`, www-data peut lire/écrire |
| Stockage | `OFFICE_STORAGE_DIR=/var/lib/office`, mode `0750`, www-data peut lire/écrire |
| Sessions | `/var/lib/php/sessions`, mode `1733`, accessible en écriture par www-data |

Les chemins persistants ne seront ni déplacés ni réinitialisés. Aucun secret n’est consigné.

## Flags et autorisations

Lecture ciblée seulement, sans afficher d’autres variables :

- Office `.env` : `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, `OFFICE_INVOICE_READ_API_ENABLED=true`, `OFFICE_STORAGE_DIR=/var/lib/office`.
- API `.env` : `INVOICE_WRITES_ENABLED` et `INVOICE_WRITE_SOURCES` absents. Ces noms sont aussi absents des environnements de processus PHP-FPM inspectés. Le code API chargé initialise l’écriture à `false` et les sources autorisées à la liste vide si absents. Aucun scope `write:invoices` n’a été demandé.
- Aucun flag, scope ou droit OAuth/OIDC n’a été changé. Aucun contexte de session OIDC opérateur n’a été utilisé.

Ces vérifications sont des preuves de fichiers/configuration et environnement de processus, pas une interrogation directe de la valeur PHP `$_ENV` d’une requête applicative. Les valeurs runtime métier restent donc à confirmer avant et après la future fenêtre ; si elles divergent ou sont incertaines, ne pas basculer.

## GET de référence (avant toute bascule)

Requêtes HTTPS GET sans cookie, sans suivi de redirection et sans corps sauvegardé :

| Hôte / chemin | Code HTTP |
|---|---:|
| `office.devsys.fr /` | 302 |
| `office.devsys.fr /assets/css/dashboard.css` | 200 |
| `office.devsys.fr /assets/js/Object/Invoices.js` | 200 |
| `office.devsys.fr /Invoices/data` | 401 |
| `api.devsys.fr /` | 404 |
| `api.spotcard.fr /` | 404 |
| `menuflow.fr /` | 200 |

La page de liste protégée n’a été testée que sans session (401). OIDC avec session valide, liste authentifiée et GET d’une facture historique ne sont pas démontrés ici. Aucun contenu facture n’a été affiché ni stocké.

## Diff exact préparé, non appliqué

Seulement dans les deux blocs du vhost Office (HTTP et HTTPS), quatre lignes de chemin :

```diff
- DocumentRoot /home/devsys/office/public
+ DocumentRoot /home/devsys/office-deploy/current/public
- <Directory /home/devsys/office/public>
+ <Directory /home/devsys/office-deploy/current/public>
```

Pas de changement de `SetHandler`, de configuration Apache globale, de pool FPM, d’autre vhost, de `.env` ou de symlink actif pendant ce préflight.

## Plan à valider pour une nouvelle fenêtre

Après seulement le GO détaillé de l’opérateur :

1. Revalider l’archive au SHA ci-dessus, le vhost et son checksum, l’état des flags runtime, les droits et cibles de `.env`, cache, stockage et sessions. Si un élément a changé, suspendre.
2. Créer une sauvegarde fraîche du vhost et un symlink `current` initial vers `/home/devsys/office` ; extraire/valider uniquement la release existante. Installer une sonde temporaire dans le `public/` de la release, refusant toute adresse non loopback et ne retournant qu’un marqueur, `PHP_VERSION` et `PHP_SAPI`.
3. Pointer atomiquement `current` vers la release approuvée. Avant tout GET, vérifier que `readlink -f current/public` résout exactement vers cette release, que `current/public/<sonde>` est un fichier réel, lisible par `www-data`, et que le `SCRIPT_FILENAME` attendu est le même chemin. Vérifier une seconde fois immédiatement avant la requête.
4. Appliquer uniquement le diff ci-dessus, `apachectl configtest`, puis reload de `apache2.service` seulement si nécessaire. Pas de reload FPM.
5. GET HTTPS loopback sans `-L` : `curl --resolve office.devsys.fr:443:127.0.0.1 https://office.devsys.fr/<sonde>`. Exiger HTTP 200, marqueur exact, PHP 8.4.x, `fpm-fcgi`, entrée dans le log Office et absence d’erreur PHP/FastCGI. Sinon : rollback immédiat.
6. Supprimer immédiatement la sonde et vérifier son absence ; le GET suivant ne doit plus renvoyer son marqueur/version. Vérifier accueil, CSS/JS, parcours OIDC seulement si une session légitime est disponible, puis liste et détail historique uniquement par GET. Ne jamais soumettre un formulaire.
7. Comparer les codes HTTP des hôtes témoins, vérifier les logs sans recopier de secrets, flags OFF, Apache/FPM actifs. Au moindre écart, restaurer la cible initiale et le fichier vhost depuis la sauvegarde, tester la configuration et recharger Apache seul si celui-ci avait été modifié. Aucun rollback de DB.

## GO opérateur et exécution de la Phase 2

Après le préflight, l’opérateur a répondu « tu peux poursuivre ». Cette confirmation a été appliquée au périmètre exact demandé : `office.devsys.fr` uniquement, fenêtre d’exécution de cette tâche (22 novembre 2026, environ 05:19–05:21 CET), archive SHA-256 `a50cdca6fd246a2a4a26bf7e7282a1e733d7a3e4c40ce2fcd6966e7f83065c01`, quatre chemins DocumentRoot/Directory décrits ci-dessus, sonde locale, symlink `current`, reload éventuel d’Apache seul et GET de lecture. Elle ne couvre ni `main`, ni push/merge, ni écriture facture.

### Bascule et preuve de sonde

- Préflight répété juste avant l’opération : SHA d’archive conforme, 241 checksums PASS, `vendor/devsys/shared` copie réelle, vhost initial conforme à la sauvegarde, `current` absent, permissions persistantes conformes, flags d’écriture OFF/absents avec défauts OFF, `apachectl configtest` et `php-fpm8.4 -t` PASS. GET de référence avant bascule : Office `/` 302, CSS/JS 200, `/Invoices/data` 401 ; API 404, SpotCard 404, MenuFlow 200.
- Sauvegarde fraîche et indépendante du contenu du vhost : `/home/devsys/office-deploy/backups/office-php84-20261122-2.2/office.devsys.fr.conf.before`, SHA-256 `92362174c580943052c9e0b360b938e316621fe6ef65f0170d2c294739eb9645`. Le fichier `sites-enabled` étant un symlink, la copie initiale de sauvegarde a été détectée comme un symlink et remplacée par une copie régulière avant toute modification du vhost.
- Sonde `__office-sapi-22-a551a6f24cdb.php` créée dans le `public/` de la release, mode `0640`, lisible par `www-data`. Le script n’incluait aucun bootstrap et ne retournait que marqueur, `PHP_VERSION`, `PHP_SAPI`; toute adresse autre que `127.0.0.1` ou `::1` recevait 404.
- `current` a été créé atomiquement vers la release. Avant édition du vhost : `readlink -f current/public` résolvait vers `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d/public`; le fichier exact `current/public/<sonde>` existait et était lisible par `www-data`.
- Seules les deux occurrences `DocumentRoot` et les deux `<Directory>` du vhost Office ont changé. SHA du fichier vhost actif après modification : `7e0fe3fee37e0012c16896bcbad7d7deebbbed595aa2755c4437fff4ed311929`. Le diff correspond exactement aux quatre lignes prévues. `apachectl configtest` : `Syntax OK`.
- Seul `apache2.service` a été rechargé. Aucun service PHP-FPM n’a été rechargé/redémarré.
- GET sans `-L`, avec Host et SNI Office épinglés sur loopback : `curl --resolve office.devsys.fr:443:127.0.0.1 https://office.devsys.fr/__office-sapi-22-a551a6f24cdb.php`. Résultat à 05:20:23 CET : HTTP 200, marqueur attendu, `8.4.24`, `fpm-fcgi`. La trace est dans le journal d’accès Office. Aucun enregistrement d’erreur corrélé à cette URI ; aucun `AH01071`.
- La sonde a été supprimée immédiatement. `test ! -e` a réussi via release et `current`. Second GET à 05:20:39 CET : HTTP 404 ; marqueur et version absents ; aucune erreur correspondante dans `error-office.log`.

### Vérifications post-bascule

| GET / contrôle | Résultat |
|---|---|
| Office `http://office.devsys.fr/` via loopback | 302 vers HTTPS |
| Office `https://office.devsys.fr/` via loopback | 302 vers `/auth/oidc/login` (query éventuelle omise) |
| CSS dashboard | 200 ; SHA servi `18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f`, identique au fichier de la release |
| JS Invoices | 200 |
| `/Invoices/data` sans session | 401, protection active |
| GET après suppression de la sonde | 404, aucun marqueur/version |
| `api.devsys.fr /` | 404, identique à avant |
| `api.spotcard.fr /` | 404, identique à avant |
| `menuflow.fr /` | 200, identique à avant |
| `apachectl configtest`; Apache/FPM actifs | PASS ; aucun reload FPM |
| Flags après bascule | `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`; clés API absentes des fichiers/environnements contrôlés, défaut API OFF et sources vides |

Aucune session OIDC opérateur n’était disponible dans le contexte d’exécution. Le flux authentifié, la liste de factures avec identité opérateur et le GET d’une facture historique n’ont donc pas été testés. Le 401 de `/Invoices/data` est le résultat sans session, pas une preuve de lecture métier réussie. Aucun formulaire n’a été soumis.

La release active est `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d`, via `/home/devsys/office-deploy/current`. Le vhost pointe vers `/home/devsys/office-deploy/current/public`. Le rollback n’a pas été déclenché, car aucun critère d’arrêt n’est apparu. La sauvegarde du vhost et l’ancienne cible `/home/devsys/office` sont conservées pour un rollback réversible.

## Tests et invariants

L’archive et les sources applicatives/lock sont strictement inchangées depuis les rapports précédents. `composer test`, `npm run build`, `git diff --check` du code et les contrôles de packaging antérieurs ne sont pas rejoués ; aucune modification de code ou packaging n’a été faite. Les contrôles de configuration et les GET effectués dans cette fenêtre sont reportés ci-dessus.

Aucun POST/PATCH/issue/delete, aucune génération de numéro, migration, restauration, mutation SQL, scope d’écriture, activation de flag ou appel externe n’a eu lieu. Aucun message, webhook, paiement ou export n’a été envoyé. Les seules modifications d’infrastructure sont le symlink `current`, les quatre chemins du vhost Office, et le reload d’Apache autorisés. La sonde a été supprimée.

## Décision finale

**`DEPLOYED_READ_ONLY_PARTIAL`** — la release candidate tourne sur le seul vhost Office et le PHP-FPM 8.4 effectif a été prouvé par une sonde loopback. Les assets et routes anonymes sont conformes aux références. La validation OIDC authentifiée et la lecture d’une facture historique restent à faire avec une session opérateur légitime ; aucune validation complète de facturation n’est revendiquée.

Les écritures de facture demeurent OFF. Ce déploiement ne donne aucune autorisation de création/modification de brouillon, émission, suppression, migration ou traitement de données réelles. Les lots requis restent le garde API tenant/principal, la restauration isolée et un GO propre pour `OFFICE-INV-RECETTE-1.2`.
