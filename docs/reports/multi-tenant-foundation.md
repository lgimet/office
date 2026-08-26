# Socle multi-tenant Office DevSys

## Périmètre

Office résout le tenant à partir de l’identité OIDC authentifiée (`tenant_uuid`
et `user_uuid`). `TenantContext` vérifie l’existence d’un tenant actif et d’une
membership active avant de fournir son identifiant interne aux repositories.
Les vues ne reçoivent pas de token ni de donnée de routage sensible.

Les repositories locaux des clients, factures et paramètres société filtrent
désormais toutes leurs lectures et écritures par `tenant_id`. Les lignes de
facture sont également vérifiées via leur facture parente. Les appels clients
passant par l’API continuent d’utiliser le contexte tenant porté par le token
OIDC.

## Migration API

La migration canonique est `Office-Api/database/migrations/018_add_office_multi_tenant_foundation.sql`.
Elle rattache `company_settings`, `invoices` et `invoice_number_sequences` au
tenant, ajoute `invoice_number_prefix`, effectue le backfill historique vers le
tenant `default-office`, puis rend les colonnes obligatoires. Elle ajoute aussi
une contrainte composite `(tenant_id, client_id)` afin d’interdire une
association facture/client inter-tenant au niveau SQL.

Les contraintes d’unicité sont désormais `(tenant_id, invoice_number)` et
`(tenant_id, year)`. Aucune facture existante n’est renumérotée et aucun
stockage tenant dédié n’est introduit.

## Numérotation

Le format est `{PREFIX}{AAAA}-{NNNN}`. Le préfixe est configurable par tenant,
limité à huit caractères alphanumériques, et la séquence est verrouillée dans
la transaction d’émission pour rester atomique par tenant et par année.

## Vérifications

`composer test` passe avec 87 tests et 152 assertions. Le lint PHP des fichiers
modifiés passe. La migration API et son rapport ont été ajoutés mais la
migration n’a pas été exécutée sur une base réelle dans cette tâche.
