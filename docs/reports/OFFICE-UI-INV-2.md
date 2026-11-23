# OFFICE-UI-INV-2 — Raccordement sécurisé des brouillons Office

Date : **21 novembre 2026 (Europe/Paris)**.

## Décision

**Non prêt pour activation.** Le flag d’écriture est documenté et faux par défaut. Aucun chemin d’écriture API n’a été branché à l’éditeur dans ce lot : une implémentation sûre ne peut pas être établie avec les contrats actuellement référencés par Office sans résoudre l’identification de propriété et du client d’un brouillon de façon durable. Les actions SQL existantes restent donc le seul comportement actif et le flag ne doit pas être mis à `true`.

Cette livraison est un audit bloquant, pas une prétention de support partiel en production. Elle n’exécute aucune migration, ne change aucun endpoint et ne manipule aucune facture.

## Base et réconciliation de l’historique

Le dépôt Office était sur `feature/office-ui-inv-1.1`, HEAD `b5c08d067f19f03ec38bd016b747fa8f0e701b62`, avec `origin/feature/office-ui-inv-1.1` au même SHA. La référence locale `origin/main` et la branche locale `main` pointent sur `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`.

La référence locale `origin/feature/office-ui-inv-1` pointe sur le merge `3529dbedf1595301f3ca9bf1edef8da602077ac0`, qui a pour parents la branche UI-INV-1 `582e31160a8a77c54fb7b387f5e616f1a36e1ffe` et UI-INV-1.1 `b5c08d067f19f03ec38bd016b747fa8f0e701b62`. Le diff d’arbre entre HEAD UI-INV-1.1 et ce merge est vide : les fichiers de lecture y sont déjà présents une seule fois, sans changement à recopier. L’arbre choisi pour ce travail correspond donc à ce merge; l’intégration historique est déjà représentée par `origin/feature/office-ui-inv-1`, mais pas par `main`.

`git fetch origin --prune` n’a pas pu rafraîchir les références : l’environnement monte `.git/FETCH_HEAD` en lecture seule. Les SHA ci-dessus sont les références locales disponibles, pas une preuve de l’état GitHub au moment de ce rapport. Aucun commit distant ou travail local n’a été écrasé. Le checkout local `../shared` est en `feature/office-shared-inv-2` à `a3abc41`; le checkout local `../api` est en `feature/office-api-inv-2.1` à `4e51226`. Ils ont servi uniquement à lire les contrats et migrations; aucun fichier de ces dépôts n’a été modifié.

## Audit du flux Office actuel

- `Invoices::save()` appelle `InvoiceService::saveDraft()`. L’ID présent dans le formulaire choisit modification SQL; sans ID, création SQL. `InvoiceService::prepareDraft()` résout le client par l’API clients puis par UUID tenant-scopé dans la base Office, calcule les montants côté Office et conserve `internal_note`.
- `Invoices::issue()` appelle `InvoiceService::issueDraft()`. Le service prépare à nouveau toutes les valeurs puis `InvoiceRepository::issueDraft()` met à jour lignes et snapshot, attribue le numéro par la séquence SQL et émet dans la même transaction.
- `Invoices::delete()` appelle `InvoiceService::deleteDraft()` et supprime le brouillon SQL. L’API `InvoicesApi` ne définit pas de suppression.
- Le flag `OFFICE_INVOICE_READ_API_ENABLED` ne change que la source de la liste. Le détail, le formulaire et PDF existants reposent sur SQL. Le `data()` API ne porte aucun marqueur de propriété exploitable par les actions de mutation.
- Le code de l’éditeur génère un aperçu JS indicatif; `save()` et `issue()` envoient les champs de formulaire et les lignes. Les handlers n’empêchent pas actuellement deux requêtes simultanées. Aucun mécanisme Office de persistance/réutilisation de clés d’idempotence n’a été trouvé.
- `OidcConfig` lit les scopes depuis `OFFICE_OIDC_SCOPES`; le scope de lecture est déjà présent. Aucun scope `write:invoices` n’est ajouté à la configuration active par ce lot. L’autorisation du client OAuth doit être préparée séparément avant tout futur essai.

## Compatibilité des champs

Le contrat `CreateInvoiceDraftRequest` et `UpdateInvoiceDraftRequest` de shared (source locale `a3abc41`) expose les données suivantes. La conversion n’est pas activée dans Office.

| Champ du formulaire | DTO / API | Valeurs vides et limites |
| --- | --- | --- |
| `client_uuid` | `clientUuid` → `client_uuid` | UUID obligatoire. Le DTO de détail retourne un snapshot client, pas son UUID. |
| `issue_date` | `issueDate` → `issue_date` | Obligatoire, date `YYYY-MM-DD`. |
| `due_date` | `dueDate` → `due_date` | `null` efface l’échéance dans un PATCH complet. Une chaîne vide côté HTML doit être normalisée en `null`. |
| `lines[].label` | `InvoiceDraftLine::label` | Obligatoire, non vide, maximum 255 caractères. |
| `lines[].description` | `description` | Facultatif; absence en création, valeur `null` explicite en mise à jour. |
| `lines[].quantity` | `quantity` | Décimal transmis en chaîne; le formulaire impose une quantité positive, le DTO accepte une chaîne décimale signée, la validation métier API doit rester l’autorité. |
| `lines[].unit` | `unit` | Option HTML vide doit devenir `null`. |
| `lines[].unit_price_excl_tax` | `unitPriceExclTax` | Décimal en chaîne; aucun total calculé par navigateur ne doit être envoyé comme référence. |
| `lines[].tax_rate` | `taxRate` | Décimal en chaîne; aucune normalisation flottante n’est sûre. |
| `lines[].discount_type` | `discountType` | `percentage`, `fixed` ou `null`. |
| `lines[].discount_value` | `discountValue` | Doit être `null` sans type de remise; l’UI envoie parfois `0` lorsque la remise est masquée. |
| `lines[].discount_note` | `discountNote` | Doit devenir `null` si vide. Le retirer dans l’UI remet aussi type et valeur à zéro. |
| `payment_terms_code` | `paymentTermsCode` | Le formulaire gère les codes connus et le code `custom`; valeur absente est `null`. |
| `payment_terms` | `paymentTerms` | Libellé personnalisé ou preset; vide doit devenir `null`. |
| `payment_method` | `paymentMethod` | Valeur du select, éventuellement vide; normalisation `null` à confirmer par la recette API. |
| `public_note` | `publicNote` | Facultative; le PATCH utilise `null` pour effacer. |
| `internal_note` | **Non supporté** | Ni create/PATCH ni le détail API ne le transportent. Tout contenu non vide interdit le chemin API; aucune suppression silencieuse n’est acceptable. |
| devise | — | Pas de champ de devise dans les DTO de commande; l’API détermine la devise. L’Office actuel affiche celle de sa configuration/snapshot SQL. |
| totaux / position | — | Calculés/réordonnés par l’API; pas de montant total ou position contractuelle dans les DTO d’écriture. |

Une remise fixe ou en pourcentage est représentable par le DTO. La UI masque parfois une remise tout en conservant sa valeur `0`; l’adaptateur devra produire `discount_type=null`, `discount_value=null`, `discount_note=null` dans ce cas, et préserver explicitement les nulls dans un remplacement complet. Les calculs arrondis JS ne sont pas une source de vérité.

## Propriété, client UUID et concurrence

`InvoiceDetails` possède `revision` et `etag` forts cohérents; `updateDraft()` exige une clé d’idempotence et un `If-Match`. En revanche, le DTO ne retourne pas `client_uuid`, `source_event_id` ni une propriété de source. `InvoicesApi::list()` n’expose pas non plus le lien source. Un GET de dernière minute fournirait bien l’ETag courant mais ne prouverait pas que l’utilisateur a modifié la version chargée à l’ouverture; ce serait incorrect pour le verrouillage optimiste.

La base partagée contient dans les migrations API locales une table `invoice_source_events` avec `tenant_id`, `source_app` et `invoice_id`, et les brouillons conservent une relation au client numérique. Cependant, ces migrations ne sont pas exécutées par ce lot et Office ne possède pas encore d’adaptateur de compatibilité lecture seule, versionné et testé contre le schéma déployé. Un registre en session Office serait perdu à l’expiration, ne couvrirait pas un autre navigateur et ne distinguerait pas les brouillons API préexistants. Il ne suffit donc pas à autoriser les actions SQL ou PATCH.

Avant d’activer l’écriture, choisir et tester une source durable d’appartenance. Recommandation : faire retourner dans le contrat API les métadonnées nécessaires (source/propriété autorisée et UUID client) ainsi que la version de création. À défaut, un adaptateur SQL strictement en lecture seule, tenant-scopé par `TenantContext`, limité aux tables et colonnes nécessaires, avec échec fermé et tests d’isolation, peut être étudié comme compatibilité temporaire. Il ne doit jamais autoriser l’écriture SQL d’un brouillon identifié API.

## Matrice de sûreté visée

| Origine / statut | Édition | Émission | Suppression | État de ce lot |
| --- | --- | --- | --- | --- |
| Historique SQL, brouillon | SQL historique | SQL historique | SQL historique | Inchangé, tant que le flag d’écriture reste faux. |
| Brouillon API `devsys` | PATCH avec ETag de l’ouverture | Bloquée visiblement dans un lot ultérieur, sauf sauvegarde confirmée puis `InvoicesApi::issue()` | Bloquée visiblement (pas de DELETE contractuel) | Non raccordé. |
| Brouillon API d’une autre source | Bloquée | Bloquée | Bloquée | L’Office ne doit pas le reclasser en historique SQL. |
| Facture émise/annulée | Lecture/PDF snapshot uniquement | Bloquée | Bloquée | Consultation historique existante; pas de mutation ajoutée. |

L’émission API pourrait être faite via `InvoicesApi::issue()`, mais l’action actuelle réémet les valeurs du formulaire et modifie aussi le brouillon. Avant de l’autoriser, l’Office doit confirmer que la totalité des changements est sauvegardée, traiter les rejoués avec GET de confirmation et désactiver les clics parallèles. L’option retenue ici est le blocage du parcours API dans ce lot. Même blocage pour suppression sans endpoint contractuel. Les chemins SQL ne doivent jamais recevoir un brouillon API.

## Idempotence et erreurs

Office ne possède pas de clés de mutation réutilisables aujourd’hui. Une implémentation future doit générer et stocker côté serveur, avec portée tenant/utilisateur/session, un `source_event_id` et une clé par tentative logique, avant le POST. Une reprise après timeout doit réutiliser exactement la même identité et le même payload; un nouvel éditeur de création volontaire reçoit une nouvelle identité. Pour PATCH, chaque tentative logique a une clé propre associée à l’ETag chargé lors de l’ouverture. Une réponse `replayed=true` peut retourner une ancienne révision et exige un GET de confirmation avant de rafraîchir la version affichée.

409 (conflit d’idempotence ou facture non modifiable), 412, 428, 401/403, 404 et réseau doivent garder les valeurs saisies, afficher une erreur compréhensible et ne jamais basculer vers SQL. Ces comportements ne sont pas implémentés ni revendiqués dans ce lot.

## Configuration, activation et retour arrière

`.env.example` expose `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, indépendant de `OFFICE_INVOICE_READ_API_ENABLED=false`. Le flag ne doit pas être activé : le chemin n’est pas implémenté. Le scope `write:invoices` n’est pas ajouté aux scopes OIDC d’exemple tant que le chemin n’est pas prêt, pour éviter qu’une copie de configuration demande prématurément une autorisation OAuth non disponible.

Avant un futur lot activable : actualiser les branches distantes, valider un contrat d’appartenance/client UUID et ses scopes, garder les deux flags faux par défaut, tests mocks, recette MySQL/API sur tenant de test, puis activation ciblée hors production. Retour arrière : remettre le flag d’écriture à `false` et redémarrer les workers; vérifier qu’aucun brouillon API n’est alors envoyé aux écritures SQL historiques.

## Modifications et vérifications

Fichiers modifiés dans Office : `.env.example`, `docs/reports/OFFICE-UI-INV-2.md`. Aucun fichier `shared` ou `Office-Api` modifié. Aucun test ciblé ajouté, car aucun chemin applicatif d’écriture n’a été livré.

- `composer test` : **120 tests, 266 assertions réussis**. Ils vérifient la régression du code existant; ils ne couvrent pas un chemin d’écriture API absent.
- `npm run build` : réussi.
- `git diff --check` : réussi.
- Aucun test MySQL/API réel, migration, ni mutation de facture exécuté.

Le `git status` final ne montre que `.env.example` et ce rapport. Les changements locaux non liés étaient absents du `git status` au début de cette tâche. Le rafraîchissement distant est resté bloqué par le montage en lecture seule de `.git`.
