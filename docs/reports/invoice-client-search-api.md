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

Les tests PHPUnit existants de lecture de facture ont été adaptés à la nouvelle dépendance de service. Les vérifications recommandées sont `composer test`, `npm run build` et `git diff --check`, complétées par un test navigateur de recherche, sélection, réouverture et émission.

## Limites / évolution future

`invoices.client_id` reste pour l’instant une FK entière historique. La résolution `UUID → ID interne` est une couche de compatibilité limitée à cette passe. Une future migration vers `invoices.client_uuid` devra être traitée séparément et n’est pas incluse ici.
