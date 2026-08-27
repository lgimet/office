# Recherche client facture via API DevSys

## État initial

Le sélecteur client des brouillons interrogeait directement la table locale `clients` depuis `InvoiceRepository`, avec une recherche SQL limitée et un identifiant entier.

## Source de vérité clients

La recherche et le chargement des coordonnées passent désormais par `DevsysClientService`, qui réutilise `ClientsApi` et le contexte tenant transmis à l’API.

## Recherche via ClientsApi

`/Invoices/clientOptions?q=...` appelle `ClientsApi::list()` avec la page 1, 25 résultats maximum, `status=active`, et un tri `display_name` ascendant. La recherche complète de l’API est donc utilisée (raison sociale, contact, e-mail, téléphone, identifiants d’entreprise et ville).

## UUID public

Le sélecteur et le champ caché du formulaire utilisent `client_uuid`, correspondant à l’UUID public de l’API. Aucun identifiant SQL n’est exposé au navigateur.

## Compatibilité avec invoices.client_id

La colonne historique `invoices.client_id` reste une FK entière. Lors de l’enregistrement, l’UUID est résolu en ID SQL uniquement après récupération du client canonique via l’API.

## Résolution UUID vers ID interne

`InvoiceRepository::clientInternalIdByUuid()` et `clientUuidForInternalId()` imposent `tenant_id = TenantContext::id()`. Elles ne chargent aucune donnée métier client.

## Snapshot client

`InvoiceService::prepareDraft()` récupère `ClientDetails` via l’API et alimente le snapshot de facture avec les données normalisées de `DevsysClientService`. Une facture émise continue ensuite d’utiliser exclusivement son snapshot persistant.

## Client actif

La liste de recherche ne retourne que les clients `active`. Une vérification supplémentaire est effectuée lors de l’enregistrement ou de l’émission afin d’empêcher l’utilisation forgée d’un UUID inactif.

## Isolation tenant

La résolution locale UUID→ID est strictement limitée au tenant courant. Un UUID valide appartenant à un autre tenant ne peut donc pas satisfaire la FK locale.

## Debounce et concurrence réseau

Le mode recherche de `CustomSelect` utilise un debounce de 275 ms et annule la requête précédente avec `AbortController`. Un compteur de requêtes protège également contre les réponses obsolètes. Le mode liste n’est pas modifié.

## Tests

Les tests PHPUnit couvrent désormais :

- les paramètres de recherche active et le mapping UUID public vers les options du sélecteur ;
- le mapping `ClientDetails` vers le snapshot, pour une société et une personne ;
- le refus d’un client inactif et d’un UUID invalide ;
- la construction du snapshot API avec résolution vers l’ID SQL interne ;
- la résolution UUID → ID et ID → UUID strictement tenant-scopée ;
- la réouverture d’un brouillon avec restauration de `client_uuid` sans appel API ;
- l’émission avec le snapshot client API courant et le blocage des clients non valides.

Les tests utilisent des réponses HTTP simulées via Guzzle et n’appellent pas l’API réelle.

Résultats vérifiés : `composer test` — 101 tests, 191 assertions ; `npm run build` — OK ; `git diff --check` — OK.

## Dépendance obligatoire

`DevsysClientService` est désormais une dépendance obligatoire de `InvoiceService`, car toute sauvegarde ou émission d’un brouillon doit utiliser le client canonique fourni par l’API. Le conteneur autowire cette chaîne via `ClientsApi` et `DevsysApiClient`.

## Limites / évolution future

`invoices.client_id` reste pour l’instant une FK entière historique. La résolution `UUID → ID interne` est une couche de compatibilité limitée à cette passe. Une future migration vers `invoices.client_uuid` devra être traitée séparément et n’est pas incluse ici.
