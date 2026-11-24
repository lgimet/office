# OFFICE-INVOICE-FACTURX-2-DEPLOY — préparation

Date : 2026-11-24 (Europe/Paris)  
État : **`BLOCKED_PREDEPLOY_READINESS`**. Cette mise à jour couvre la préparation en lecture seule ; aucun GO de déploiement n'a été reçu.

## Sources et version servie

Après rafraîchissement Git, `office/main` est `2c520d234b83ca36211705c82a22f2e790f8bdcf`, `Office-Api/main` `2d47da238a2066a35e18e22ae574a94f4ab1d962`, et `shared/main` `d1fd8eda2e0c5eb5b5ef40dbe8dc9813330030b8`. Le verrou Office et le SDK installé dans la release active restent épinglés au commit `d2b52a83a7265b4fa158090549462ec72c26ad45`.

`/home/devsys/office-deploy/current` et le DocumentRoot actif pointent vers `office-invoice-facturx1-20261123-7e962ea`. Son archive est `office-invoice-facturx1-20261123-7e962ea.tar.gz`, SHA-256 `63205f00ee1c8758830ffa54b145f08075e99096307c99533afbf841dc248951` ; le sidecar et le manifest interne sont valides. Cette release est Factur-X 1. Le code Factur-X 2 de `main` n'est pas encore packagé ni servi. Le flag `OFFICE_INVOICE_FACTURX_ENABLED` est absent de `.env`, donc OFF selon le défaut applicatif ; il n'a pas été changé.

## Contrôles runtime

- Apache et PHP-FPM 8.4 sont actifs ; `apachectl configtest` et `php-fpm8.4 -t` réussissent. Le DocumentRoot pointe sur un chemin absolu de release.
- `.env` Office est un fichier externe, mode `0640`, propriétaire/groupe `devsys:www-data`, lisible par le service. `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED` reste ON ; aucune configuration n'a été modifiée. Le préflight API documenté contrôle ce flag et l'allowlist API actifs et refusera la bascule si leur état n'est pas admissible.
- Le SDK est présent et autoloadable dans la release active ; aucune mise à jour de `composer.lock` ou de `vendor` n'a eu lieu.
- La préparation a confirmé que les scripts API de release pilotent aussi les vhosts `login.devsys.fr`. Aucun hôte login n'est autorisé par le périmètre courant et aucun reload n'a été effectué.
- Les tests `composer test` et `npm run build` déjà consignés dans le rapport de merge concernent les sources fusionnées et fixtures. Ils n'ont pas été rejoués lors de cette inspection opérationnelle ; aucun candidat de release n'a été construit.

## État Factur-X 2 et limites

L'API active ne contient pas la migration 026 et la base `office` n'a pas la table d'archives : voir le rapport de préparation Office-Api. La release Office ne peut donc pas être activée avant une release API compatible et l'application contrôlée de 026. Aucun `FACTURX_ARCHIVE_DIR` n'est configuré. Le banc `/home/devsys/facturx-toolchain` est privé et inaccessible par traversée à `www-data` ; aucun droit n'a été modifié et aucun outil n'est déclaré opérationnel pour PHP-FPM.

Aucune facture n'a été générée, archivée, téléchargée ou émise. Les factures `2026-0001` et `2026-0002`, les données métier, OIDC, les pointeurs `current`, vhosts, flags, services et permissions sont inchangés. Aucune requête de production ni aucune migration n'a été exécutée.

## Blocage et reprise

État : **`BLOCKED_PREDEPLOY_READINESS`**, pas `READY_FOR_DEPLOY_GO`. Il reste à approuver une procédure API compatible avec l'exclusion de login, régler le conflit entre le préflight de release et les gates d'écriture actuellement actifs, vérifier une sauvegarde/restauration isolée de la base, et préparer le stockage/outillage privés accessibles au runtime sans exposer les fixtures ni le banc de qualification.

Le GO ultérieur devra nommer les hôtes, les SHA exacts des sources, les IDs et SHA-256 des releases, la base cible `office`, la migration 026 et son SHA, la fenêtre, les reloads autorisés, les états de flags, le backup restaurable et le rollback code/vhosts. Les scripts historiques qui touchent login ne seront pas utilisés sous le GO actuel. Les réserves documentées demeurent : avertissement Schematron `PEPPOL-EN16931-R008`, incertitude sur la destination d'une ancienne tentative cURL exploratoire et absence de certification juridique française exhaustive.
