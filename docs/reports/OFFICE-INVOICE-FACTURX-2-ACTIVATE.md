# OFFICE-INVOICE-FACTURX-2-ACTIVATE

Le 24 novembre 2026, les flags Factur-X API et Office ont été activés (`ACTIVATED_WITH_LIMITATION`). Releases actives : API `api-invoice-facturx2-20261124-7f88c7c-r1`, Office `office-invoice-facturx2-20261124-2c520d2`. Les gates de brouillon/écriture sont restés inchangés. Santé API 200 ; routes statut API et téléchargement Office sans authentification refusées en 401 ; table d’archives vide. Aucun document n’a été généré : pas de fixture de test autorisée, aucune facture réelle utilisée. Le warning R008 et la limite de validation française restent explicitement documentés.

Rapport API détaillé : [OFFICE-INVOICE-FACTURX-2-ACTIVATE.md](https://github.com/lgimet/Office-Api/blob/docs/office-invoice-facturx-2-deploy-result/docs/reports/OFFICE-INVOICE-FACTURX-2-ACTIVATE.md). Le job quotidien de sauvegarde est toujours en échec selon son dernier état connu ; voir les détails dans ce rapport.

## Correctif de la modale info — 24 novembre 2026

La boîte `confirm-box info` pouvait perdre son fond sur une page sans classe de thème `.light`/`.dark` : elle est ajoutée directement à `document.body`, tandis que ses tokens de couleur étaient définis dans le thème. Le style utilise désormais des couleurs de secours opaques pour la boîte et son bouton.

PR Office #17 fusionnée : https://github.com/lgimet/office/pull/17 ; `office/main` : `c08b558b9249d8705842d465b83d93ee7cd605f2`. Release active : `office-invoice-facturx2-20261124-c08b558`. `npm run build` et `git diff --check` : PASS. La feuille `dashboard.css` servie en HTTP 200 contient la règle de fond de secours ; racine Office 302, santé API 200. Retour arrière : restaurer le vhost sauvegardé sous `/home/devsys/office-deploy/backups/confirm-info-modal-20261124` et `current` vers `office-invoice-facturx2-20261124-2c520d2`, puis `apachectl configtest` et reload gracieux Apache. Aucun flag, vhost login, service FPM ou code métier n'a été modifié.
