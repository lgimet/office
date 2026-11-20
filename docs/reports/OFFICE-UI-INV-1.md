# OFFICE-UI-INV-1 — Lecture des factures via shared/InvoicesApi

Date : **20 novembre 2026 (Europe/Paris)**.

## Sources et révisions consultées

| Dépôt | Branche / révision | Usage |
| --- | --- | --- |
| `office` | `main`, `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` | Contrôleur, services, repository, vues, JS, DI, fournisseur OIDC, tests et dépendances. Branche de travail créée : `feature/office-ui-inv-1`. |
| `shared` | `main`, `2a15ecfa6794e090ec6ea941a2ecc62b3e139c12` (référence `composer.lock`) | `InvoicesApi`, DTO de requête/liste/détail, client HTTP, exceptions et rapport `OFFICE-SHARED-INV-1.md`. Lecture seule. |
| `Office-Api` | `main`, rapport `OFFICE-API-INV-2.md` indiquant `ae64c533c7e72a92e30523b732cf17e65b83e6cc` | Contrôleur de lecture, repository PDO, autorisation tenant et rapports `OFFICE-API-AUTH-1.md` et `OFFICE-API-INV-3.md`. Lecture seule. |

Les fichiers du dépôt Office reflètent le checkout `main` indiqué. Le rapport partagé confirme les types DTO effectivement installés par le dépôt path Composer `../shared`. L’API utilise un bearer utilisateur et son contexte tenant serveur; la query API est composée seulement de `page`, `per_page`, `search` et `status`.

## Modifications

- `src/Services/DevsysInvoiceReadService.php` appelle exclusivement `InvoicesApi::list(InvoiceListQuery)` et adapte les DTO à l’ancien format de table `rows/pages`. Les montants sont copiés comme chaînes décimales, sans conversion flottante ni calcul métier. Un brouillon sans numéro reçoit `Brouillon #<id>`.
- `src/Controllers/Invoices.php` sélectionne cette liste uniquement avec `OFFICE_INVOICE_READ_API_ENABLED=true`. Sinon `InvoiceService::list()` continue d’utiliser le repository SQL comme avant. Les échecs API produisent un HTTP non réussi et un message lisible; le journal ne contient que statut et request ID. Aucun fallback automatique n’est tenté.
- `config/services.php` autowire `InvoicesApi` et le service d’adaptation à partir du `DevsysApiClient` déjà configuré. Ce dernier continue à obtenir le token OIDC utilisateur par `OfficeAccessTokenProvider`.
- `.env.example` documente le flag désactivé par défaut.
- `tests/Unit/DevsysInvoiceReadServiceTest.php` vérifie le mapping mixte, la pagination vide, le bornage des filtres, l’absence de sélecteur tenant dans la requête HTTP et la propagation des erreurs API.
- `tests/Unit/InvoicesDataSwitchTest.php` vérifie le défaut SQL, la sélection explicite de l’API, l’absence de fallback et les réponses 401/403/404/422/500, JSON mal formé et panne réseau.

Aucun changement dans `shared` ou `Office-Api`, aucune requête HTTP directe, migration SQL ou route nouvelle. Aucun POST API n’est appelé.

## Parcours et compatibilité

| Parcours | Ce lot | Contrat / motif |
| --- | --- | --- |
| Liste `GET /Invoices/data` | API si flag activé, SQL historique sinon | Mappe `id`, `reference`, nom snapshot client, dates, statut et montants; `pages` vaut au moins 1 pour satisfaire la table actuelle. |
| Détail `Invoices::view()` | SQL historique | L’API fournit client et émetteur sous forme de snapshots, montants, conditions de paiement, note publique et lignes. Elle ne fournit pas `internal_note`, affichée par `view.twig`; le détail n’est donc pas équivalent. |
| PDF `Invoices::pdf()` | SQL historique inchangé | `InvoicePdfService`/`InvoiceTemplateResolver` ont besoin des champs aplatis `issuer_*`, `pdf_template_source` et `pdf_template_version`. Les métadonnées du modèle historique sont absentes du DTO et les snapshots ne sont pas fournis dans la forme attendue du template PDF. |
| Formulaire, création, édition, suppression, émission | SQL historique inchangé | `form`, `saveDraft`, `deleteDraft`, `issueDraft` et les méthodes de `InvoiceRepository` ne sont pas modifiés. Aucun numéro ni document n’a été créé ou émis par ce lot. |

Les propriétés `invoice_number`, `id` et `status` sont gardées dans les lignes pour les actions existantes edit/delete/view. La vue, le PDF, `DynamicTable.js` et `Invoices.js` ne sont pas changés.

## Filtres et différences de recherche

Le service borne `limit` à 1–100, `page` à une plage entière positive supportée par le SDK, filtre les statuts aux trois valeurs connues, et tronque la recherche à 120 octets UTF-8 afin de respecter le contrat `InvoiceListQuery`. Les paramètres `tenant_id` et `tenant_uuid`, même s’ils étaient ajoutés par un appelant, ne sont jamais transmis. L’API et Office-Api n’acceptent pas de tenant depuis le navigateur.

La recherche SQL historique couvre `invoice_number`, `client_name`, `client_email` et l’ID. L’API ne couvre que `invoice_number` et le snapshot `client_name`, avec recherche limitée à 120 octets et échappement des jokers `%`, `_` et `!`. La recherche par e-mail ou ID disparaît donc quand le flag API est actif. Aucun SQL parallèle ne complète le résultat. Tester cette différence dans l’interface avant toute activation opérateur.

## Activation et retour arrière

Par défaut, `OFFICE_INVOICE_READ_API_ENABLED=false` : comportement SQL historique. Pour activer après validation opérationnelle, définir `OFFICE_INVOICE_READ_API_ENABLED=true` dans l’environnement de l’application puis redémarrer/recharger les workers PHP selon le déploiement. Vérifier une page et ses filtres avec un utilisateur OIDC autorisé à `read:invoices`.

En cas d’erreur, les réponses 401 et 403 restent des refus visibles; 404 demeure un 404; les erreurs réseau, validation inattendue, structure DTO invalide et erreurs 5xx sont renvoyées comme indisponibilité (503). Aucun cas ne repasse silencieusement au SQL. Pour le retour arrière, remettre le flag à `false` et recharger l’application; le tableau revient alors au chemin SQL historique.

## Vérifications

| Commande | Résultat |
| --- | --- |
| `composer test` | **119 tests, 255 assertions réussis**. |
| `npm run build` | Réussi; script Sass du dépôt exécuté. |
| `git diff --check` | Réussi. |
| `php -l` sur le service, contrôleur et configuration DI | Réussi. |

Le test API utilise un `MockHandler` Guzzle; aucun appel réseau réel n’a été fait. Les migrations 021/022 ne sont ni appliquées ni modifiées dans ce lot. L’autowiring utilise la version `devsys/shared` épinglée en path dans `composer.lock`.

## Suite proposée

Avant activation de la bascule, faire valider la perte des recherches email/ID et vérifier les scopes/session OIDC en environnement cible. Un lot séparé peut enrichir le contrat de détail avec `internal_note` si son exposition est voulue et gérer explicitement les métadonnées de template PDF; il faudra alors tester les snapshots complets sans détourner le PDF historique. Les mutations restent hors périmètre tant qu’un lot dédié ne traite pas leur migration et l’idempotence.
