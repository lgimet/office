# Authentification des utilisateurs

## Périmètre

Cette documentation décrit la connexion des utilisateurs de l’interface Office SaaS.

Elle ne concerne pas les endpoints API internes, qui utilisent une clé Bearer `API_INTERNAL_KEY` distincte. Consultez la [documentation d’authentification API](api/authentication.md) pour les appels machine à machine.

## Configuration

Les variables suivantes doivent être définies dans le fichier `.env` local ou dans les variables d’environnement du serveur :

```dotenv
JWT_SECRET=une-valeur-longue-aleatoire-et-secrete
JWT_EXPIRATION=3600
JWT_REFRESH_EXPIRATION=1209600
```

- `JWT_SECRET` signe les JWT avec l’algorithme HS256. Il ne doit jamais être versionné ni communiqué à un client.
- `JWT_EXPIRATION` définit la durée de vie du jeton d’accès, en secondes. La valeur par défaut est `3600`.
- `JWT_REFRESH_EXPIRATION` définit la durée de vie du jeton de rafraîchissement, en secondes. La valeur par défaut est `1209600` (14 jours).

## Stockage des mots de passe

La table `users` ne contient aucun mot de passe en clair.

- Le champ utilisé est `password_hash`.
- À la création ou lors d’un changement de mot de passe, le hash est généré avec `password_hash($plainPassword, PASSWORD_DEFAULT)`.
- À la connexion, le mot de passe saisi est vérifié avec `password_verify()`.
- Après une connexion réussie, un hash devenu obsolète est renouvelé automatiquement avec `password_needs_rehash()`.

L’adresse e-mail est normalisée en minuscules avant la recherche. Seuls les utilisateurs actifs (`is_active = 1`) peuvent se connecter.

## Connexion

```text
POST /Auth/login
Content-Type: application/json
```

Corps de la requête :

```json
{
  "email": "utilisateur@example.com",
  "password": "mot-de-passe"
}
```

Cette requête utilise la protection CSRF de l’application web. Depuis l’interface Office SaaS, le jeton CSRF est ajouté automatiquement.

En cas d’échec, l’application renvoie volontairement le message générique suivant :

```text
Adresse e-mail ou mot de passe incorrect.
```

Ce message ne permet pas de déterminer si l’adresse e-mail existe.

## Session utilisateur

Après une connexion réussie, l’application crée deux cookies :

| Cookie | Rôle |
| --- | --- |
| `auth_token` | JWT d’accès à courte durée de vie. |
| `refresh_token` | JWT de rafraîchissement à durée de vie plus longue. |

Les cookies sont configurés avec les attributs suivants :

- `HttpOnly` : le JavaScript du navigateur ne peut pas lire les jetons ;
- `SameSite=Lax` ;
- `Path=/` ;
- `Secure` lorsque la connexion est servie en HTTPS.

Le JWT d’accès contient l’identifiant utilisateur et les informations d’identité nécessaires à l’interface. Il est signé avec `JWT_SECRET`.

Le JWT de rafraîchissement n’est pas conservé en clair en base de données : seul son hash SHA-256 est enregistré dans `user_tokens`, avec une date d’expiration. Une seule session de rafraîchissement valide est conservée par utilisateur.

## Rafraîchissement automatique

Lorsqu’une route protégée est appelée :

1. l’application valide `auth_token` ;
2. si le jeton est expiré, absent ou invalide, elle tente de valider `refresh_token` ;
3. le hash du jeton de rafraîchissement est comparé à celui stocké dans `user_tokens` ;
4. si la vérification réussit, une nouvelle paire de cookies est émise ;
5. sinon, la session est invalidée et l’utilisateur doit se reconnecter.

Les contrôleurs protégés utilisent l’attribut `#[AuthRequired]`.

## Déconnexion

```text
POST /Auth/logout
```

La déconnexion :

1. supprime le hash de rafraîchissement de l’utilisateur dans `user_tokens` ;
2. expire les cookies `auth_token` et `refresh_token` ;
3. détruit la session PHP ;
4. régénère l’identifiant de session avant le retour à la page de connexion.

## Recommandations d’exploitation

- Servir la production exclusivement en HTTPS afin d’activer l’attribut `Secure` des cookies.
- Utiliser un `JWT_SECRET` long, aléatoire et propre à chaque environnement.
- Ne jamais enregistrer, journaliser ou transmettre les mots de passe et les JWT en clair.
- Ne pas réutiliser `API_INTERNAL_KEY` comme `JWT_SECRET`.
- Désactiver un compte via `users.is_active` pour empêcher toute nouvelle connexion.
