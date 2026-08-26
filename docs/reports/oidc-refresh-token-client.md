# Office — Refresh Token OIDC

## Implémentation

Le callback Authorization Code exige désormais `access_token`, `refresh_token`, `token_type=Bearer`, `expires_in` positif et `id_token` avant de créer la session. Le token set est stocké uniquement dans `$_SESSION['office_oauth']`; le refresh token n’est pas copié dans `office_identity`, `AuthService::verify()`, les vues, JavaScript, les URLs ou les logs.

`OidcTokenRefresher` utilise le `token_endpoint` issu de la discovery OIDC avec une requête POST Basic `office-web:client_secret` contenant exclusivement `grant_type=refresh_token` et le refresh token courant. Aucun `resource`, `tenant_id`, `subject`, `audience` ou `scope` n’est envoyé par défaut.

Lorsque l’Access Token atteint la marge d’expiration de 30 secondes, `OfficeAccessTokenProvider` effectue une seule tentative de refresh. La réponse est validée entièrement avant remplacement atomique de l’Access Token, du refresh token et de l’expiration. Un éventuel `scope` remplace les scopes de l’identité locale. Les erreurs `invalid_grant`, `invalid_client`, réponses invalides et erreurs réseau purgent la session et redirigent vers un nouveau login; aucun retry du même refresh token n’est effectué.

Le logout existant détruit la session complète et supprime donc également le token set. Aucun changement n’a été effectué dans `Office-Api` ou `office-mcp`.

## Tests et vérifications

Les tests couvrent le stockage initial du refresh token, son exigence au callback, l’absence de refresh pour un Access Token valide, la requête Basic de refresh sans paramètres métier, la rotation access/refresh, la mise à jour des scopes, les réponses invalides, `invalid_grant`, la purge et la redirection, ainsi que l’absence de retry après erreur réseau.

`composer validate` passe avec l’avertissement préexistant de licence manquante. `composer dump-autoload --no-interaction` passe. `composer test` passe avec 80 tests et 125 assertions. Le lint PHP passe sur `src` et `tests`.

Le test d’intégration avec un Authorization Server réel supportant la rotation obligatoire du refresh token reste à exécuter après déploiement. Validation manuelle attendue : simuler un Access Token proche de l’expiration, ouvrir une page appelant l’API métier, vérifier un seul POST refresh vers `login.devsys.fr`, la réussite de l’appel API et le remplacement du refresh token, sans afficher de token complet.
