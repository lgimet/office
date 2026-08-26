# Émission définitive des factures

## État initial

Office permettait de créer, modifier et supprimer des brouillons. Le projet
contenait déjà la table `invoice_number_sequences` et un générateur de numéro,
mais aucune action complète de passage de `draft` à `issued` n’était exposée.

## Fichiers inspectés

- `src/Controllers/Invoices.php`
- `src/Services/InvoiceService.php`
- `src/Services/InvoiceCalculationService.php`
- `src/Repositories/InvoiceRepository.php`
- `src/Services/InvoiceNumberGenerator.php`
- `src/Views/Invoices/form.twig`
- `src/Views/Invoices/list.twig`
- `public/assets/js/Object/Invoices.js`
- `src/Repositories/CompanySettingsRepository.php`
- `src/Services/CompanySettingsService.php`
- `database/003_create_invoices.sql`

## Fichiers modifiés

- `src/Controllers/Invoices.php`
- `src/Services/InvoiceService.php`
- `src/Repositories/InvoiceRepository.php`
- `src/Services/InvoiceNumberGenerator.php`
- `public/assets/js/Object/Invoices.js`
- `docs/reports/invoice-issue-finalization.md`

## Workflow d’émission

Un brouillon enregistré affiche l’action « Émettre la facture ». Une nouvelle
facture sans identifiant ne l’affiche pas. Avant l’appel `POST /Invoices/issue`,
le formulaire est validé et une confirmation explicite est demandée. Le
payload courant, y compris les lignes et montants visibles, est envoyé au
service.

Le service sauvegarde d’abord ce payload comme brouillon, puis demande son
émission. Cela garantit qu’une modification non sauvegardée ne soit pas
ignorée.

## Contrôles avant émission

Les contrôles existants de `saveDraft()` sont réutilisés : facture existante et
modifiable, client valide, date cohérente, lignes valides et recalculs via
`InvoiceCalculationService`. Les snapshots client, conditions de paiement,
mode de paiement, montants et notes sont donc enregistrés avant le passage à
`issued`. Aucune nouvelle obligation arbitraire dans `company_settings` n’a été
ajoutée.

## Numérotation

Le format est `F{AAAA}-{NNNN}`, par exemple `F2026-0001`. L’année provient de
`issue_date` et le compteur repart à `0001` pour chaque nouvelle année.

## Transaction et concurrence

`InvoiceRepository::issueDraft()` ouvre une transaction, verrouille la facture
avec `SELECT ... FOR UPDATE`, vérifie qu’elle est encore `draft` et sans numéro,
puis utilise `invoice_number_sequences`. Le générateur verrouille la séquence
de l’année avec `SELECT ... FOR UPDATE`, incrémente `last_number`, puis met à
jour la facture dans la même transaction. Toute erreur déclenche un rollback.

Une seconde émission de la même facture est refusée avant toute consommation
de numéro. Deux factures différentes obtiennent des numéros distincts grâce au
verrouillage de la ligne de séquence.

La date transmise au générateur est désormais celle du payload courant préparé
et écrit (`issue_date` finale), et non plus l’ancienne date relue lors du
verrouillage du brouillon. Un changement de millésime avant émission produit
donc un numéro cohérent avec la facture, tout en conservant la transaction
unique et le verrouillage de séquence.

## Verrouillage après émission

Le backend conserve les garde-fous de `updateDraft()` et `deleteDraft()` : toute
facture dont le statut n’est plus `draft` est refusée. L’interface ferme la
fenêtre après succès et recharge la liste ; les actions Modifier et Supprimer
restent réservées aux brouillons.

## Comportement frontend

Après émission, un toast affiche le numéro définitif et la liste est rechargée.
La liste utilise déjà `invoice_number` lorsqu’il existe et conserve
`Brouillon #123` pour les brouillons sans numéro. Le badge `issued` est déjà
affiché comme « Émise ».

## Compatibilité avec les brouillons existants

Aucune migration n’a été ajoutée dans `office`. Le schéma existant contient
`invoice_number`, `status`, `issued_at` et `invoice_number_sequences`. Aucun
PDF, Factur-X, Docoon, e-mail ou mécanisme de transmission n’a été développé.

## Tests

Vérifications prévues :

- `npm run build`
- `node --check public/assets/js/Object/Invoices.js`
- `composer test`
- `git diff --check`

La couverture PHPUnit existante valide les non-régressions ; les scénarios
transactionnels nécessitent une base MySQL de test configurée pour être
exécutés en intégration.

## Résultat

Le workflow `draft → issued` est disponible avec numérotation annuelle atomique,
prise en compte du payload courant et verrouillage backend après émission.

## Passe corrective — contrôle émetteur et atomicité

Avant toute émission, `CompanySettingsService::validateIssuerForInvoice()` exige
la raison sociale, l’adresse, le code postal, la ville, le pays et au moins un
SIREN ou un SIRET. Le contrôle intervient avant l’opération d’émission et donc
avant toute génération ou incrément de numéro.

La préparation des données du formulaire a été factorisée dans
`InvoiceService::prepareDraft()`. `issueDraft()` ne lance plus `saveDraft()` :
il prépare le payload courant, valide l’émetteur, puis appelle une seule
opération repository transactionnelle. Celle-ci verrouille le brouillon,
réécrit son snapshot et ses lignes, verrouille et incrémente la séquence,
attribue le numéro et passe la facture à `issued` avant le commit.

Ainsi, une erreur avant le commit annule simultanément la sauvegarde, les lignes
et la séquence. Le verrouillage de la facture conserve également la protection
contre la double émission. Les tests de concurrence MySQL réels ne sont pas
exécutables dans l’environnement courant ; les garde-fous transactionnels et
les tests PHPUnit existants restent vérifiés.
