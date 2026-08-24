# Liste des clients via l’API Devsys

La liste des clients d’Office est alimentée côté serveur par l’API métier Devsys. Le navigateur appelle toujours Office ; il n’appelle jamais directement `api.devsys.fr`.

```text
Navigateur → Office (/Clients/data) → api.devsys.fr/api/v1/clients
```

## Configuration

Renseigner les variables suivantes dans `.env` :

```dotenv
DEVSYS_API_BASE_URL=https://api.devsys.fr/api/v1
DEVSYS_API_TOKEN=
DEVSYS_API_TIMEOUT=10
```

`DEVSYS_API_TOKEN` est envoyé uniquement dans l’en-tête HTTP serveur `Authorization: Bearer …`. Il ne doit jamais être exposé dans Twig, JavaScript ou une réponse AJAX.

## Correspondance des paramètres

| DynamicTable Office | API Devsys |
| --- | --- |
| `page` | `page` |
| `limit` | `per_page` |
| `search` | `search` |
| `type` | `type` |
| `status` | `status` |
| `sort=client_name` | `sort=display_name` |
| `sort=client_type_name` | `sort=type` |
| `sort=email` | `sort=email` |
| `sort=city` | `sort=city` |
| `sort=status` | `sort=status` |
| `sort=created_at` ou `updated_at` | même valeur |
| `dir` | `direction` |

Les valeurs sont normalisées et filtrées par `ClientListQuery` avant tout appel HTTP. Les pages commencent à 1, `per_page` est limité à 100 et le tri est limité à la liste ci-dessus.

## Adaptation de la réponse

La réponse métier est convertie en DTO (`ClientListResult`, `ClientListItem`, `Pagination`) puis en contrat `DynamicTable` :

```json
{
  "rows": [],
  "pages": 1,
  "page": 1,
  "per_page": 25,
  "total": 0
}
```

En cas de délai dépassé, d’erreur réseau, d’erreur d’authentification ou de réponse API invalide, Office renvoie un message générique : « La liste des clients est temporairement indisponible. » Les détails techniques restent limités aux logs serveur et ne contiennent jamais le jeton.

## Limites actuelles du contrat API

L’endpoint métier actuel retourne un identifiant UUID, `display_name`, `type`, `email`, `phone`, `city` et `status`. Il ne retourne ni le contact, ni les types locaux `SpotCard` / `ORepas` / `Autre`, ni les données nécessaires à la fiche locale Office. La colonne Contact est donc vide et le filtre utilise les types réellement supportés par l’API (`company`, `association`, `individual_business`, `individual`).

Les actions de modification locales ne sont pas affichées dans cette liste : elles attendent encore un identifiant numérique de la base Office et ne seraient pas fonctionnelles avec un UUID Devsys. Leur migration nécessite des endpoints métier de consultation et de modification, hors du périmètre de cette bascule de liste.
