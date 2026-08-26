# Diagnostic des erreurs de validation d’identité OIDC

## Objectif

Rendre les erreurs du callback OIDC exploitables côté serveur sans modifier le
comportement de validation ni exposer de données OAuth sensibles.

## Fichiers inspectés

- `src/Services/Oidc/OidcClient.php`
- `src/Services/Oidc/OidcIdTokenValidator.php`
- `src/Services/Oidc/OidcErrorContext.php`
- `src/Controllers/Oidc.php`
- `tests/Unit/OidcErrorContextTest.php`

## Fichiers modifiés

- `src/Services/Oidc/OidcErrorContext.php`
- `tests/Unit/OidcErrorContextTest.php`
- `docs/reports/oidc-identity-error-diagnostics.md`

## Cause du manque de diagnostic

Le callback encapsule les erreurs du validateur dans une
`OidcIdentityException`. Le mécanisme de log enregistrait le type et le message
de l’exception principale ainsi que sa trace, mais ne parcourait pas la chaîne
accessible par `getPrevious()`. La cause précise, par exemple une audience ou
une signature invalide, n’était donc pas visible de façon fiable.

## Nouvelle journalisation

`OidcErrorContext::log()` journalise maintenant le type et le message de
l’exception principale, puis chaque exception précédente sous la forme :

```text
OIDC previous: App\\Services\\Oidc\\OidcValidationException: Audience ID Token invalide.
```

La chaîne est limitée à dix niveaux et un éventuel dépassement est signalé sans
faire échouer le callback. La trace complète n’est plus journalisée.

## Protection contre les fuites

Les messages sont nettoyés avant journalisation : les valeurs associées à
`access_token`, `refresh_token`, `id_token`, `client_secret`, `nonce`, aux
cookies et à l’autorisation sont remplacées par `[redacted]`. Les JWT détectés
sont également masqués et les retours à la ligne sont supprimés.

## Tests

Les tests couvrent :

- une exception sans cause précédente via les tests de classification existants ;
- une cause précédente ;
- plusieurs causes imbriquées ;
- l’absence de fuite de valeurs de tokens et de secret ;
- la non-régression des tests OIDC.

## Vérifications

Résultat : `composer test` doit rester entièrement vert. La reproduction
opérationnelle consiste à refaire le callback OIDC en erreur puis à rechercher
les lignes `OIDC callback` et `OIDC previous` dans le log serveur.

## Limites et périmètre

Ce correctif ne conclut pas sur une éventuelle anomalie `iat`, `nbf`, d’horloge,
de JWKS ou de `firebase/php-jwt`; il rend seulement sa cause lisible si elle
survient. Aucune règle de validation OIDC, aucun flux PKCE, refresh token,
redirection, session ou page d’erreur n’a été modifié.
