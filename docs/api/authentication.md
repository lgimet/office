# Authentification de l’API

## Principe

Les endpoints API internes d’Office SaaS utilisent une clé statique transmise avec l’en-tête HTTP `Authorization`.

Il n’y a pas de session navigateur, de cookie ni de point de connexion utilisateur à appeler pour ces endpoints. L’API est stateless : chaque requête doit transmettre la clé Bearer.

Ce mécanisme est différent de l’authentification des utilisateurs de l’interface web, documentée dans [l’authentification utilisateur](../authentication-utilisateur.md).

## Configuration serveur

Définir une clé longue et aléatoire dans le `.env` du serveur :

```dotenv
API_INTERNAL_KEY=une-cle-longue-aleatoire-et-secrete
```

Ne jamais versionner cette clé, l’exposer dans une application front-end ou la transmettre dans une URL.

## En-tête à envoyer

```http
Authorization: Bearer <API_INTERNAL_KEY>
Accept: application/json
```

Pour les requêtes avec un corps JSON, ajouter :

```http
Content-Type: application/json
```

## Exemple d’appel — lister les types de clients

```bash
export OFFICE_API_KEY='votre-cle-api'

curl --request GET 'https://office.devsys.fr/api/v1/client-types' \
  --header "Authorization: Bearer ${OFFICE_API_KEY}" \
  --header 'Accept: application/json'
```

Réponse :

```json
{
  "success": true,
  "client_types": [
    {
      "id": 1,
      "name": "SpotCard",
      "slug": "spotcard"
    }
  ]
}
```

## Exemple d’appel — créer un client

```bash
export OFFICE_API_KEY='votre-cle-api'

curl --request POST 'https://office.devsys.fr/api/v1/clients' \
  --header "Authorization: Bearer ${OFFICE_API_KEY}" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{
    "client_type_slug": "spotcard",
    "company_name": "Restaurant du Centre",
    "email": "contact@restaurant.fr",
    "city": "Montpellier",
    "country": "France"
  }'
```

Une création réussie renvoie `201 Created`.

## Erreurs d’authentification

Une clé absente ou invalide renvoie une réponse `401 Unauthorized` :

```json
{
  "success": false,
  "message": "Clé API invalide."
}
```

## Utilisation dans Postman

1. Importer [la collection Office SaaS](../postman/office-api.postman_collection.json).
2. Ouvrir les variables de collection.
3. Renseigner `base_url` et `api_internal_key`.
4. Exécuter les requêtes souhaitées.

## Limites actuelles

La clé `API_INTERNAL_KEY` donne accès à tous les endpoints API internes disponibles. Il n’existe pas encore de gestion de clients OAuth, de scopes ni de jetons utilisateur API individuels.

Si un accès tiers différencié est nécessaire, il faudra mettre en place un mécanisme dédié, par exemple OAuth 2.0 ou des clés API par intégration avec des droits limités.
