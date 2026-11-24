# OFFICE-INVOICE-FACTURX-2-ACTIVATE

Le 24 novembre 2026, les flags Factur-X API et Office ont été activés (`ACTIVATED_WITH_LIMITATION`). Releases actives : API `api-invoice-facturx2-20261124-7f88c7c-r1`, Office `office-invoice-facturx2-20261124-2c520d2`. Les gates de brouillon/écriture sont restés inchangés. Santé API 200 ; routes statut API et téléchargement Office sans authentification refusées en 401 ; table d’archives vide. Aucun document n’a été généré : pas de fixture de test autorisée, aucune facture réelle utilisée. Le warning R008 et la limite de validation française restent explicitement documentés.

Rapport API détaillé : [OFFICE-INVOICE-FACTURX-2-ACTIVATE.md](https://github.com/lgimet/Office-Api/blob/docs/office-invoice-facturx-2-deploy-result/docs/reports/OFFICE-INVOICE-FACTURX-2-ACTIVATE.md). Le job quotidien de sauvegarde est toujours en échec selon son dernier état connu ; voir les détails dans ce rapport.
