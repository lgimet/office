# OFFICE-INVOICE-MVP-1-ENABLE-TEST-1.1 — Office

Date : 2026-11-23 (Europe/Paris)

## Décision

`OPERATOR_ACTION_REQUIRED`. Le scope a été ajouté à la configuration Office et l'opérateur déclare s'être reconnecté ; je n'ai pas pu observer le bearer de cette session. Les diagnostics et écritures restent OFF. Aucun brouillon n'a été créé ou modifié.

## Références et état actif

- `office/main` : `8150e7fa4956f8bdc85ef337950b8100c5c20b30`; release `office-invoice-mvp1-20261123-8150e7f`.
- `Office-Api/main` : `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`; release API/login `api-invoice-mvp1-20261123-4f5baec`.
- SDK partagé installé/verrouillé : `b845450b2d4004fa31a5e3a540184fff42c7ebba`.
- `current` pointe vers ces releases. `OFFICE_OIDC_SCOPES` a une affectation unique et inclut `write:invoices`; l'identifiant client configuré est celui d'Office. Aucun override FPM correspondant observé. L'autorisation côté serveur et le scope effectivement délivré restent à confirmer par le diagnostic utilisant le vrai bearer.
- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, diagnostics Office absents/OFF ; API write gate et garde absents/OFF, allowlist vide, paramètres privés absents. Modes des `.env` : `0640`.
- Office GET `/Invoices/recipePreflight` retourne 405 (route POST-only). La route API diagnostic renvoie 404 quand OFF. Aucune tentative POST n'a été faite.

## Architecture et limites d'observation

Le callback OIDC valide l'ID token, compare les claims UserInfo, puis construit la session. `TenantContext` part du principal et du tenant de session et valide une membership active. `OfficeAccessTokenProvider` fournit le token d'accès utilisateur de cette session au client SDK partagé. Le diagnostic existant passe par le SDK et laisse l'API tester `write:invoices` dans le bearer réel, ainsi que l'identité et le tenant établis par ses middlewares. La simple possibilité de lire les listes ne démontre que le parcours de lecture.

La session navigateur n'est pas pilotable par Codex ; je n'ai lu ni session PHP, ni cookie, ni bearer. Aucune identité n'a été déduite de la base. Le client fictif n'a pas été identifié/confirmé par Codex.

## Une action opérateur

Un opérateur/administrateur autorisé valide en privé le `sub` OIDC déjà vérifié et le tenant effectif/membership active avec les outils OIDC/TenantContext établis. Il configure les attentes privées du diagnostic déployé et une lease absolue de 300 s maximum (`STARTED_AT`, `EXPIRES_AT`, `BOOT_ID`) sur API et Office, sans ouvrir les write gates. Dans la session Office déjà reconnectée, il ouvre DevTools → Console et envoie le POST same-origin suivant avec le CSRF fourni normalement par la page :

```js
(async () => {
  const response = await fetch('/Invoices/recipePreflight', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.CSRF_TOKEN
    },
    body: '{}'
  });
  const body = await response.json();
  console.log({ status: response.status, result: body.result ?? 'UNAVAILABLE' });
})();
```

Aucun bouton UI dédié n'est présent ; ce POST utilise le routeur authentifié et le contrôle CSRF existant, et affiche uniquement statut/résultat. Dans la liste Office déjà accessible, l'opérateur confirme également que le client fictif préexistant est visible dans son tenant et ne rapporte que le booléen `TEST_CLIENT_VISIBLE`. Ensuite il retourne uniquement `PASS/REFUSED/UNAVAILABLE` et ce booléen ; il ne communique aucun identifiant privé ou secret. Il referme immédiatement les deux flags diagnostics et efface leurs attentes privées.

Si le résultat est `PASS` et `TEST_CLIENT_VISIBLE=true`, Codex pourra vérifier les seules valeurs de statut des guards côté serveur sans les afficher, puis activer les protections et write flags dans l'ordre du rapport API, avec l'opérateur présent. Tant que cette preuve n'existe pas, ne pas soumettre le formulaire : Office OFF pourrait basculer vers SQL historique.

## Non exécuté

Session bearer/scope réel : `NOT_RUN`. Sujet/tenant effectifs : `NOT_RUN`. Client fictif : `NOT_VERIFIED`. Diagnostic et mutation : `NOT_RUN`. Aucun code ou configuration n'a été modifié ; tests `NOT_APPLICABLE (inspection only)`.
