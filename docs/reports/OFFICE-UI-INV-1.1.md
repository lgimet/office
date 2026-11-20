# OFFICE-UI-INV-1.1 — Scope OIDC et parité de recherche

Date : **20 novembre 2026 (Europe/Paris)**.

## Révisions et branches

| Dépôt | Base consultée | Branche de correction |
| --- | --- | --- |
| `office` | `main` `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`; le lot UI-INV-1 est sur `feature/office-ui-inv-1` `582e311` et n’est pas intégré à main. | `feature/office-ui-inv-1.1` basée sur `582e311`. |
| `Office-Api` | `origin/main` `19625c6` (merge INV-3); le checkout main local était en retard. | `feature/office-api-inv-2.1` basée sur `origin/main` `19625c6`. |
| `shared` | `main` `2a15ecfa6794e090ec6ea941a2ecc62b3e139c12`, tel que référencé par `office/composer.lock`. | Lecture seule. |

Le `feature/office-api-inv-3` local (`7b0bd86`) était antérieur au merge actuel de main; le correctif API part donc bien du main distant le plus récent. Aucun changement dans `shared` ou `office-mcp`.

## Office — demande du scope de lecture

`.env.example` ajoute `read:invoices` à `OFFICE_OIDC_SCOPES` et conserve `openid`, `profile`, `email`, `read:clients`, `write:clients` et `delete:clients`. Aucun scope `write:invoices` n’est demandé. `OidcConfigTest` vérifie les scopes chargés et relit l’exemple pour détecter une dérive de configuration.

`OidcClient::authorizationUrl()` transmet cette liste à la connexion existante. Le refresh conserve les scopes indiqués par le serveur lorsqu’ils sont retournés; aucun changement OIDC n’a été fait. La configuration d’exemple ne change ni le `.env` déployé ni le client OAuth `office-web`. L’opérateur doit autoriser explicitement `read:invoices` pour `office-web`; une nouvelle connexion ou un renouvellement de token de test peut être nécessaire pour obtenir le scope.

Le flag `OFFICE_INVOICE_READ_API_ENABLED=false` reste inchangé par défaut. Aucun droit d’écriture, paramètre OAuth de production ou secret n’a été modifié.

## Office-Api — recherche liste

`PdoInvoiceReadRepository::search()` ajoute à la recherche existante `i.client_email` (snapshot de la facture) et `CAST(i.id AS CHAR)`. Les quatre motifs SQL utilisent des paramètres préparés distincts (`search_number`, `search_client`, `search_email`, `search_id`) à partir de la même valeur échappée.

Les filtres tenantés restent dans COUNT et SELECT; statut, limites, offset, ordre `created_at DESC, id DESC` et forme de réponse sont conservés. La liste ne sélectionne ni ne retourne l’e-mail, `internal_note`, ni d’identifiant tenant. Le détail, les routes, les migrations et les écritures ne changent pas.

Les tests SQLite couvrent recherche par numéro, nom snapshot, e-mail snapshot et ID; deux tenants; pagination/total/ordre/statut; changement d’e-mail de la fiche client courante sans effet sur la recherche snapshot; `%`, `_`, `!` littéraux; et motif d’injection sans résultat. Différence mineure avec l’ancien SQL Office : `%` et `_` sont cherchés littéralement (comportement plus sûr) au lieu d’agir comme jokers.

## Fichiers modifiés

**Office**

- `.env.example`
- `tests/Unit/OidcConfigTest.php`
- `docs/reports/OFFICE-UI-INV-1.1.md`

**Office-Api**

- `src/Infrastructure/Persistence/Invoice/PdoInvoiceReadRepository.php`
- `tests/Unit/Infrastructure/Persistence/Invoice/PdoInvoiceReadRepositoryTest.php`
- `docs/reports/OFFICE-API-INV-2.1.md`

Le code d’adaptation et de sélection de liste du lot initial, `Invoices.js`, Twig, l’éditeur, l’émission, la suppression, le PDF et les séquences ne changent pas.

## Activation et retour arrière

Ordre opérateur recommandé : déployer d’abord Office-Api et valider la recherche en recette; vérifier ensuite que `office-web` est explicitement autorisé pour `read:invoices`; mettre à jour la variable `OFFICE_OIDC_SCOPES` d’Office selon sa procédure d’environnement et ouvrir une nouvelle session de test. Tester les quatre recherches avec un utilisateur ayant un membership actif et le scope requis.

Après intégration des deux branches et validation, activer séparément `OFFICE_INVOICE_READ_API_ENABLED=true` pour Office. En cas de problème, remettre ce flag à `false` et recharger Office; la liste SQL historique revient. Le flag reste faux dans le code et dans `.env.example`.

## Recette HTTP à effectuer par l’opérateur

Aucun endpoint de production ou de préproduction n’a été appelé et aucun bearer n’est conservé. Avec un utilisateur de test autorisé et des données fictives, appeler en GET seulement :

```text
GET /api/v1/invoices?page=1&per_page=25
GET /api/v1/invoices?search=<numero>
GET /api/v1/invoices?search=<nom-client>
GET /api/v1/invoices?search=<email-snapshot>
GET /api/v1/invoices?search=<id>
GET /api/v1/invoices?search=<terme>&status=issued&page=1&per_page=10
```

Confirmer la pagination et le tenant courant, qu’un bearer sans scope reçoit 403 et que la lecture d’un ID d’un autre tenant reste un 404 indistinguable lorsque ces identités et données de recette sont disponibles. Puis tester la page Office avec le flag actif. Ne pas mettre de token dans les commandes partagées, journaux ou rapports. Aucun numéro n’est créé et aucune facture n’est émise.

## Vérifications

| Dépôt | Commandes et résultats |
| --- | --- |
| Office | `composer test` : **120 tests, 266 assertions réussis**; `npm run build` réussi; `git diff --check` réussi. |
| Office-Api | `composer test -- --do-not-cache-result` : **215 tests, 737 assertions**, 6 ignorés et 6 dépréciations PHPUnit, aucun échec; `git diff --check` à exécuter après rapport; `npm run build` non applicable, aucun `package.json`. |

Les tests repository utilisent SQLite mémoire. Aucun test HTTP avec bearer réel ni base MySQL/MariaDB connectée n’a été exécuté; vérifier `CAST(... AS CHAR)` sur la version MySQL/MariaDB cible lors de la recette avant activation.
