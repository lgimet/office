# Sauvegarde multi-tenant de `company_settings`

## Bug initial

L’ancienne requête déclarait 28 colonnes SQL, dont `tenant_id`, mais seulement
27 placeholders, alors que le service fournissait 28 paramètres : le tenant et
27 valeurs métier. La sauvegarde pouvait donc échouer avant même l’application
de la logique multi-tenant.

## Source du tenant courant

Le tenant est résolu côté serveur par `TenantContext`, à partir de l’identité
OIDC et de la membership active. `CompanySettingsRepository` utilise toujours
`TenantContext::id()` pour la lecture et l’écriture. Un `tenant_id` ou
`tenant_uuid` fourni par le navigateur n’est ni lu ni accepté pour sélectionner
la société.

## Correction du repository

La lecture reste filtrée par `WHERE tenant_id = ?`. La sauvegarde utilise un
UPSERT protégé par l’unicité `(tenant_id)` de la migration API. Le tenant est
ajouté aux paramètres dans le repository, puis les valeurs métier sont liées
avec des placeholders nommés (`:legal_name`, `:default_currency`, etc.). Il n’y
a plus de tableau positionnel fragile entre le service et la requête.

## Structure des données normalisées

`CompanySettingsService::normalize()` retourne désormais une structure
associative contenant les champs légaux, bancaires, fiscaux, le préfixe de
facture et les conditions de paiement normalisées. Les validations existantes
sont conservées : raison sociale, e-mail, TVA, devise, préfixe et code de délai.
La validation complète de l’émetteur reste réservée à l’émission d’une facture.

## Isolation inter-tenant

Chaque tenant possède au plus une ligne `company_settings`. Un enregistrement
pour un tenant crée ou met à jour uniquement sa ligne ; les paramètres d’un
autre tenant ne sont jamais sélectionnés par identifiant technique `id`.

## Compatibilité avec l’émission des factures

`InvoiceService::issueDraft()` continue de charger les informations de
l’émetteur via `CompanySettingsRepository::find()`, donc via le tenant courant.
`invoice.client_id` reste exclusivement le destinataire de la facture.

## Tests

La couverture vérifie la normalisation associative, l’utilisation effective du
`tenant_id` courant dans les paramètres nommés et l’ignorance d’un `tenant_id`
forgé dans le payload. La suite Office passe avec 89 tests et 158 assertions.
La migration API `018_add_office_multi_tenant_foundation.sql`
fournit l’unicité et la clé étrangère nécessaires ; aucune nouvelle migration
n’a été créée pour ce correctif.
