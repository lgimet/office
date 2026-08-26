# Délai de paiement et calcul de l’échéance

## Contexte

Le formulaire de facture propose désormais un délai de paiement prédéfini et calcule l’échéance à partir de la date de facture.

## État initial

`payment_terms` était un champ texte libre. L’échéance n’était pas recalculée côté interface.

## Fichiers inspectés

- `src/Views/Invoices/form.twig`
- `public/assets/js/Object/Invoices.js`
- `src/Controllers/Invoices.php`
- `src/Services/InvoiceService.php`
- `src/Repositories/InvoiceRepository.php`
- styles Invoice SCSS et CSS objet

## Fichiers modifiés

- `src/Views/Invoices/form.twig`
- `public/assets/js/Object/Invoices.js`
- `public/assets/css/scss/components/_invoice.scss`
- `public/assets/css/objects/Invoices/form.css`

## Contrat payment_terms observé

L’API et le stockage attendent une chaîne métier lisible. Le select utilise des codes uniquement côté UI; le champ `payment_terms` sérialisé contient toujours le libellé métier ou le texte personnalisé.

## Presets implémentés

Comptant, À réception, 15 jours, 30 jours, 45 jours, 60 jours, 30 jours puis fin de mois, 45 jours puis fin de mois, Fin de mois + 30 jours, Fin de mois + 45 jours et Personnalisé.

## Codes UI utilisés

`cash`, `receipt`, `days_15`, `days_30`, `days_45`, `days_60`, `days_30_then_eom`, `days_45_then_eom`, `eom_plus_30`, `eom_plus_45` et `custom`.

## Calcul des délais simples

Les règles comptant et à réception conservent la date de facture. Les autres délais simples ajoutent 15, 30, 45 ou 60 jours calendaires. Les règles fin de mois ajoutent d’abord le délai, puis prennent le dernier jour du mois obtenu.

## Calcul N jours puis fin de mois

Le délai est ajouté en premier, puis la date est positionnée au dernier jour du mois obtenu.

## Calcul fin de mois + N jours

La date est d’abord positionnée au dernier jour du mois de facture, puis le délai est ajouté.

## Gestion du mode personnalisé

Le champ « Libellé du délai personnalisé » apparaît uniquement pour `Personnalisé`. L’échéance devient éditable et le texte saisi alimente `payment_terms`.

## Compatibilité anciennes valeurs

Les libellés connus sont remappés vers leur preset. Toute valeur libre inconnue est conservée dans le champ personnalisé et le preset `custom` est sélectionné.

## Comportement des factures existantes

L’échéance existante n’est pas modifiée au chargement. Elle est recalculée uniquement après changement du délai ou de la date de facture lorsqu’un preset est actif.

## Gestion des fuseaux horaires

Les dates sont parsées explicitement en année/mois/jour et reconstruites localement au format `YYYY-MM-DD`, sans parsing direct d’une chaîne ISO UTC.

## Années bissextiles

Le calcul repose sur `Date` local, qui gère notamment le 29 février des années bissextiles.

## Évolution du code métier

La structuration persistante `payment_terms_code` est documentée dans `invoice-payment-terms-code.md`. Les libellés `Fin de mois + N jours` et les codes `eom_plus_*` ont été retirés de l’interface.

## Responsive

Le champ personnalisé reste dans le même champ de grille et suit les breakpoints existants de l’éditeur de facture.

## Tests exécutés

- `npm run build`
- `composer test`
- `git diff --check`

## Résultats

Suite verte : 83 tests et 134 assertions.

## Points restant éventuellement à traiter

Les scénarios visuels aux dimensions 1366×768, 768×1024 et 390×844 restent à vérifier dans un navigateur sur un environnement web accessible.
