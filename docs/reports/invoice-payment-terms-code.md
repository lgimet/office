# Code métier des délais de paiement

## État initial

Office pilotait les presets côté interface mais ne persistait que le libellé dans `payment_terms`.

## Fichiers inspectés

- `src/Views/Invoices/form.twig`
- `public/assets/js/Object/Invoices.js`
- `src/Controllers/Invoices.php`
- `src/Services/InvoiceService.php`
- `src/Services/CompanySettingsService.php`
- `src/Repositories/InvoiceRepository.php`
- `src/Repositories/CompanySettingsRepository.php`
- `docs/reports/invoice-payment-terms-due-date.md`
- dépôt `../api`, schéma historique et migrations

## Fichiers modifiés

- `src/Views/Invoices/form.twig`
- `public/assets/js/Object/Invoices.js`
- `src/Services/InvoiceService.php`
- `src/Services/CompanySettingsService.php`
- `src/Repositories/InvoiceRepository.php`
- `src/Repositories/CompanySettingsRepository.php`
- `docs/reports/invoice-payment-terms-code.md`

La migration et son rapport sont exclusivement dans le dépôt API autorisé `../api`.

## Select final

Le select contient Comptant, À réception, 15 jours, 30 jours, 45 jours, 60 jours, 30 jours fin de mois, 45 jours fin de mois et Personnalisé. Les choix `Fin de mois + N jours` ont été supprimés.

## Mapping code / libellé

`cash` → Comptant, `receipt` → À réception, `days_15`, `days_30`, `days_45`, `days_60`, `days_30_then_eom`, `days_45_then_eom` et `custom`. Les codes restent invisibles pour l’utilisateur.

## Calcul des échéances

Les calculs restent isolés de `recalculate()`. Les délais simples ajoutent des jours calendaires; les variantes fin de mois ajoutent d’abord le délai puis prennent le dernier jour du mois obtenu.

## Nouvelle facture

Le code de délai par défaut de la société est prioritaire. À défaut, le libellé existant est reconnu, puis `custom` est utilisé si nécessaire. L’échéance initiale est calculée en JavaScript selon le code sélectionné; aucun `+15 jours` fixe ne subsiste dans Twig.

## Facture existante

`payment_terms_code` est prioritaire au chargement. Sans code, le libellé est reconnu avec compatibilité legacy. La `due_date` existante n’est jamais recalculée au chargement.

## Mode personnalisé

Le code `custom` conserve le libellé saisi et rend l’échéance libre. Le champ technique du select n’est pas envoyé à la place du libellé; Office envoie séparément `payment_terms_code`, `payment_terms` et `due_date`.

## Payload envoyé à l’API

Les repositories Office incluent désormais `payment_terms_code` dans les insertions et mises à jour, sans supprimer `payment_terms`.

## Compatibilité legacy

Les anciens libellés connus sont remappés. Toute valeur inconnue devient `custom` sans perte du texte. Les deux anciennes branches `eom_plus_30` et `eom_plus_45` ne sont plus acceptées par l’UI ni par le calcul frontend.

## Tests

- `npm run build`
- `node --check public/assets/js/Object/Invoices.js`
- `composer test`
- `git diff --check`

## Résultat

Suite Office verte : 83 tests et 134 assertions. La migration API n’a pas été exécutée sur une base réelle.
