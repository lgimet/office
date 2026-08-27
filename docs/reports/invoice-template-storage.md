# Socle multi-tenant et versionné des modèles PDF de facture

## Architecture

Cette passe prépare la résolution des modèles sans générer de PDF. Les modèles système suivent `resources/pdf/invoices/default/{version}/`. Les modèles personnalisés sont résolus sous `OFFICE_STORAGE_DIR/tenants/{storage_key}/invoices/templates/{version}/`, hors de `public/` et hors du dépôt.

## Tenant et sécurité

`TenantRepository` expose désormais `storage_strategy`, `storage_key` et `storage_state` dans le tenant résolu. `InvoiceTemplateResolver` utilise `storage_key`, ou `tenant.uuid` si la clé est vide, après validation stricte `[A-Za-z0-9._-]+`. Les versions suivent `v1`, `v2`, etc. Aucun chemin ni identifiant de tenant ne vient du navigateur.

## Versions et fallback

La version système courante est `v1`. Une version personnalisée est lue depuis `company_settings.invoice_template_version`; une valeur nulle utilise le système. Un modèle configuré mais incomplet lève une erreur explicite et ne bascule pas silencieusement. `first-page.pdf` est obligatoire ; `continuation.pdf` peut retomber sur `first-page.pdf`.

## Snapshot sur facture

La migration `020_add_invoice_template_version.sql` ajoute `company_settings.invoice_template_version`, `invoices.pdf_template_source` et `invoices.pdf_template_version`, tous compatibles avec les brouillons. Les factures historiques `issued`/`cancelled` sont initialisées en `system/v1` uniquement si les deux champs sont nuls.

Lors de l’émission, `InvoiceService` résout le descriptor courant et transmet uniquement `source` et `version` au repository. Le repository les écrit dans la même transaction que le snapshot émetteur, les lignes, le numéro définitif et le statut `issued`. Les chemins physiques ne sont jamais persistés. `resolveForInvoice()` utilise exclusivement la source/version figées de la facture historique.

Une version déjà utilisée ne doit jamais être écrasée : une évolution graphique crée une nouvelle version.

## Tests et vérifications

Les tests couvrent le fallback système, le modèle tenant, la clé de stockage sûre, la continuation optionnelle, le refus d’un modèle incomplet, la résolution historique et la transmission source/version à l’émission. Aucun PDF réel ni appel réseau n’est requis.

Résultats : Office `composer test` — 107 tests, 208 assertions ; `npm run build` — OK ; `git diff --check` — OK. Office-Api `composer test` — 192 tests, 631 assertions ; `git diff --check` — OK. Le dépôt API étant monté en lecture seule, PHPUnit signale uniquement l’impossibilité d’écrire son cache de résultats.
