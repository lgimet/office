# OFFICE-INVOICE-FACTURX-2 — interface Office

Date : 2026-11-23
Statut : **`IMPLEMENTED_TESTED_NOT_DEPLOYED`**

- Base `main` observée : `7e962eaabd80ea5b9dbdbe4b43f43a4206e9217b`.
- Branche `feature/office-invoice-facturx-2`, commit de code `bae7dba0d2d4e057f8056cb9a8e69a17e9bc5df9`.
- La vue API émise peut afficher l’état de l’archive ; l’action de génération est explicite et confirmée, le téléchargement lit l’archive via service Office → SDK `shared`. Le flag `OFFICE_INVOICE_FACTURX_ENABLED` reste désactivé par défaut. La route PDF historique n’est pas remplacée pour les factures historiques.
- Le `composer.lock` épingle shared au SHA `ab5ba633dcb3247bb7f6751f0801bd4fca97da77`; `vendor/devsys/shared` vérifié à ce SHA. Guzzle `7.17.0` est verrouillé ; aucun paquet flottant ni `composer update` non verrouillé n’est présent.
- Résultats : **171 tests / 519 assertions**, `npm run build`, lint ciblé, `git diff --check`, `composer audit --locked --no-dev`, validation et exigences plateforme ont passé. Les tests Office ne réalisent pas une navigation de navigateur ni un téléchargement API réel.

Aucun changement de `main`, merge, déploiement, configuration production ou facture réelle n’a eu lieu. Le statut global reste partiel tant que l’archivage et la sécurité multi-tenant ne sont pas exercés sur un MySQL isolé ; voir le rapport API.
