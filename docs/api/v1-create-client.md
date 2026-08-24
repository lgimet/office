# Créer un client

## Endpoint

```text
POST /api/v1/clients
```

## Authentification

Envoyer la clé définie dans `API_INTERNAL_KEY` avec l’en-tête suivant :

```text
Authorization: Bearer <API_INTERNAL_KEY>
```

## Corps de la requête

```json
{
  "client_type_slug": "spotcard",
  "company_name": "Restaurant du Centre",
  "display_name": "Restaurant du Centre",
  "contact_first_name": "Paul",
  "contact_last_name": "Martin",
  "email": "contact@restaurant.fr",
  "phone": "04 00 00 00 00",
  "address_line1": "10 rue des Marchands",
  "address_line2": null,
  "postal_code": "34000",
  "city": "Montpellier",
  "country": "France",
  "siret": "12345678900012",
  "vat_number": "FR12123456789",
  "notes": null
}
```

`client_type_slug` et `company_name` sont obligatoires. Le type doit être actif. Les autres champs sont optionnels ; `country` vaut `France` par défaut.

## Réponse de succès — 201

```json
{
  "success": true,
  "client": {
    "id": 42,
    "client_type": {
      "name": "SpotCard",
      "slug": "spotcard"
    },
    "company_name": "Restaurant du Centre",
    "display_name": "Restaurant du Centre",
    "email": "contact@restaurant.fr",
    "city": "Montpellier"
  }
}
```

## Erreur métier — 422

```json
{
  "success": false,
  "message": "Le type de client demandé n’existe pas.",
  "errors": {
    "client_type_slug": [
      "Le type de client demandé n’existe pas."
    ]
  }
}
```

Une clé absente ou invalide renvoie `401`.
