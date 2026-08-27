# Snapshot immuable de l’émetteur

## Pourquoi

Une facture émise doit conserver l’identité et les coordonnées de l’émetteur telles qu’elles existaient à l’émission. Une modification ultérieure de `company_settings` ne doit pas changer une facture historique ni une future régénération de document.

## Champs snapshotés

`issuer_legal_name`, `issuer_trading_name`, `issuer_legal_form`, `issuer_share_capital`, `issuer_address_line1`, `issuer_address_line2`, `issuer_postal_code`, `issuer_city`, `issuer_country`, `issuer_email`, `issuer_phone`, `issuer_website`, `issuer_siret`, `issuer_siren`, `issuer_vat_number`, `issuer_ape_code`, `issuer_rcs_city`, `issuer_bank_name`, `issuer_iban`, `issuer_bic` et `issuer_invoice_footer`.

La devise, le taux de TVA, les conditions de paiement et le préfixe de numérotation ne sont pas snapshotés ici : ils sont déjà matérialisés par la facture et son numéro.

## Migration et backfill legacy

La migration `019_add_invoice_issuer_snapshot.sql` ajoute des colonnes nullable à `invoices`. Elle backfill uniquement les factures `issued` ou `cancelled`, via la correspondance `invoices.tenant_id = company_settings.tenant_id`, et uniquement lorsque `issuer_legal_name` est encore nul. Les brouillons ne sont pas backfillés. Pour les anciennes factures, il s’agit d’un best effort fondé sur les meilleures données actuellement disponibles.

## Moment du snapshot et atomicité

`InvoiceService::issueDraft()` lit les `company_settings` du tenant courant, les valide, les normalise via `invoiceIssuerSnapshot()`, puis transmet le snapshot à `InvoiceRepository::issueDraft()`. Le repository l’écrit après le verrouillage du brouillon et le recalcul, dans la même transaction que le numéro définitif et le passage à `issued`. Une erreur entraîne le rollback.

## Isolation et immutabilité

Les données de l’émetteur viennent exclusivement de `CompanySettingsRepository::find()`, déjà tenant-scopé par `TenantContext`. Aucun champ `issuer_*` fourni par le navigateur n’est utilisé. Les méthodes de brouillon ne modifient jamais les champs `issuer_*`; ils sont écrits uniquement dans le chemin d’émission.

## Tests

Les tests couvrent le mapping des champs historiques et l’exclusion des paramètres de configuration non concernés, ainsi que la transmission du snapshot par le chemin d’émission. Résultats vérifiés : dans Office, `composer test` — 102 tests, 197 assertions ; `npm run build` — OK ; `git diff --check` — OK. Dans Office-Api, `composer test` — 192 tests, 631 assertions ; `git diff --check` — OK. PHPUnit a signalé un avertissement de cache (`.phpunit.result.cache`) car le dépôt API est monté en lecture seule, sans impact sur le résultat des tests.
