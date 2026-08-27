# Vue de consultation des factures émises

## État initial

Les brouillons utilisaient l’éditeur de facture. Aucune vue dédiée ne permettait
de consulter une facture après émission sans repasser par ce formulaire.

## Séparation brouillon / facture émise

`InvoiceService::form()` continue d’accepter uniquement les brouillons. La
nouvelle méthode `view()` accepte uniquement les statuts `issued` et
`cancelled`, et charge la facture ainsi que ses lignes via le repository déjà
scopé par `TenantContext`.

## Route et service de consultation

`POST /Invoices/view` rend `src/Views/Invoices/view.twig`. La réponse utilise
le numéro définitif comme titre de fenêtre. Une facture inexistante ou un
brouillon consulté par cette route produit une erreur explicite.

## Données affichées

La fiche est entièrement en lecture seule : numéro, statut, dates, paiement,
devise, client, lignes, remises, notes et totaux sont rendus avec des éléments
sémantiques, sans champ `input` ou `select` éditable.

## Snapshots client

Les coordonnées proviennent exclusivement des colonnes snapshot de `invoices`
(`client_name`, adresse, téléphone, e-mail, SIRET et TVA). La vue ne recharge
pas la fiche client et n’appelle pas `/Invoices/clientOptions`.

## Totaux persistés

Les montants affichés viennent des valeurs enregistrées lors de l’émission et
aucun recalcul JavaScript ou appel à `InvoiceCalculationService` n’est effectué
pour cette fiche.

## Navigation depuis la liste

La liste propose `Voir la facture` pour `issued` et `cancelled`, avec l’icône
`bi-eye-fill`. Les actions de modification et suppression restent limitées aux
brouillons.

## Navigation après émission

Après succès de l’émission, le brouillon est fermé et la fiche de consultation
de la facture est ouverte automatiquement dans le shell de fenêtres Office.

## Isolation tenant

La consultation réutilise `InvoiceRepository::find()` et `lines()`, qui
appliquent `TenantContext` et `tenant_id`. Un identifiant appartenant à un autre
tenant est donc introuvable pour la fiche courante.

## Correctif de chargement CSS de la vue

`Core::loadWindowCss()` charge la feuille de style à partir de l’objet et de
l’action. Ainsi, `/Invoices/view` attend spécifiquement
`/assets/css/objects/Invoices/view.css`. Ce fichier n’existait pas : la fiche
pouvait sembler correctement stylée uniquement lorsque `form.css` avait déjà
été chargé auparavant.

`public/assets/css/objects/Invoices/view.css` contient désormais les styles
communs réellement utilisés par la fiche et ses styles spécifiques. La vue est
ainsi autonome lors d’une ouverture directe après un rechargement d’Office,
comme après l’émission depuis le formulaire.

## Tests

La couverture vérifie la consultation d’une facture émise avec ses lignes,
l’erreur sur facture inexistante et le refus de traiter un brouillon comme une
facture émise. La suite passe avec 92 tests et 162 assertions.
