# OFFICE-INVOICE-FACTURX-2-MERGE — Office

Date : 2026-11-24 (Europe/Paris)  
Statut : **`FACTURX_2_MERGED_NOT_DEPLOYED`**.

- PR [office #14](https://github.com/lgimet/office/pull/14) fusionnée par GitHub après `shared` puis `Office-Api`. Commit de code de la PR : `bd34db1a634ebe468cdb16c1d75b57f8b7fa4b32` ; commit de merge et `main` : `2c520d234b83ca36211705c82a22f2e790f8bdcf`.
- Office propose des actions explicites de génération/téléchargement via le service Office → SDK `shared` → API. `OFFICE_INVOICE_FACTURX_ENABLED` reste OFF par défaut. Aucun appel HTTP direct ni SQL local de facture n'a été ajouté ; le chemin PDF historique reste inchangé.
- `composer.lock` et le `vendor` du worktree pointent vers `devsys/shared` au SHA fixe `ab5ba633dcb3247bb7f6751f0801bd4fca97da77`, maintenant atteignable depuis `shared/main`. Aucun paquet flottant.
- Tests : `composer test` **171 tests / 519 assertions**, `npm run build`, validation Composer, exigences plateforme, audit Composer verrouillé, lint ciblé et `git diff --check` : PASS.
- L'intégration locale a vérifié les octets du SDK jusque dans la réponse du contrôleur Office, sans ouvrir une session navigateur Office complète. La réserve du rapport API reste applicable : la destination d'une tentative GET exploratoire initiale n'est pas vérifiable ; les essais finaux étaient exclusivement loopback. `PEPPOL-EN16931-R008` et l'absence de validation juridique française exhaustive restent documentés.
- Aucun changement de release, `current`, vhost, `.env`, flag de production ni donnée facture. La migration API 026 est fusionnée dans le dépôt API mais non appliquée en production. Les dernières releases rapportées (non recontrôlées ici) restent API `api-invoice-facturx1-20261123-bc6be3c` et Office `office-invoice-facturx1-20261123-7e962ea`.

Le rapport de fusion principal et les SHAs des trois dépôts sont dans [Office-Api](https://github.com/lgimet/Office-Api/blob/main/docs/reports/OFFICE-INVOICE-FACTURX-2-MERGE.md). Le flag doit rester OFF jusqu'au GO distinct de déploiement.
