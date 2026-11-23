# OFFICE-INVOICE-DRAFT-WRITE-1

**Date:** 2026-11-23 (Europe/Paris)  
**Résultat phase A:** `BLOCKED_AUTHENTICATED_SESSION`  
**Phase mutatrice:** non exécutée ; aucun GO mutateur reçu.

## Résumé

Les releases Office et API attendues sont toujours actives. Les contrôles GET anonymes répondent conformément aux protections attendues. Les tests isolés API et Office passent. Aucun formulaire n'a été soumis, aucun POST/PATCH/issue/delete n'a été envoyé, aucun accès SQL ni changement de configuration n'a été réalisé.

La recette ne peut pas passer à la demande de GO mutateur : aucune session OIDC opérateur légitime n'est disponible dans cette exécution, et le client fictif préexistant / son appartenance au tenant courant ne peuvent donc pas être validés. En outre, les valeurs privées tenant/sujet du garde sont absentes et aucun mécanisme indépendant d'expiration de la fenêtre de flags n'est armé. Aucun secret, sujet ou identifiant tenant n'est consigné ici.

## Références et runtime constatés

| Composant | Référence | État observé |
|---|---|---|
| Office, rapport de recette antérieur | `work/office-invoice-draft-recette-1` `162480c4f8ea04bf76825c8c4cfd73a047ba8978` | Rapport `READ_ONLY_PARTIAL`; aucune session authentifiée démontrée. |
| Office, source fonctionnelle déployée | `work/office-invoice-web-deploy-1` `ccbba1150bf5769a84b522e056c417e7b7c34f70` | Lignée issue de `feature/office-inv-recette-1.2` `64b6b298c6dc049c06b671e02e813b93255da090`. |
| Office runtime | `office-invoice-web-20261123-64b6b29-0661b92` | `/home/devsys/office-deploy/current` résout vers cette release. |
| API garde | `work/office-api-guard-integration-1` `a07573566f2f08986afe70f722c714a0615a046c` | Garde déjà déployé, sans changement dans ce lot. |
| API runtime | `api-invoice-draft-guard-20261123-aa9be47-r1` | `/home/devsys/api-deploy/current` résout vers cette release ; archive précédemment vérifiée dans le rapport de déploiement. |
| SDK | `devsys/shared` `0661b92b914c28c41f45f5bd19bb9a860c232676` | Commit réellement installé dans le `vendor` de la release Office. |
| API JWT | `firebase/php-jwt` `v7.2.2` | Vérifié dans le `vendor` de la release API. |

Les références distantes ont été rafraîchies. `office/main` est `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`, `Office-Api/main` est `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf`, `shared/main` est `83e5b7da32ae20039218586e18dc93ee9ad95852`. La branche `work/office-invoice-draft-write-1` est créée depuis la tête distante du rapport de recette Office, pas depuis `main`.

## Préflight lecture seule

Les `current` Office/API et leurs quatre vhosts API/login ont été inspectés sans modification. Contrôles HTTPS GET via loopback/Host/SNI :

| Route | Résultat | Portée de la preuve |
|---|---:|---|
| `office.devsys.fr/` | 302 | Redirection d'accueil existante. |
| `office.devsys.fr/Invoices/data` sans session | 401 | Protection anonyme active. |
| `api.devsys.fr/api/v1/health` | 200 | Santé publique API. |
| `api.devsys.fr/api/v1/invoices` sans bearer | 401 | Route protégée sans authentification. |
| `login.devsys.fr/.well-known/openid-configuration` | 200 | Découverte OIDC. |
| `login.devsys.fr/.well-known/jwks.json` | 200 | JWKS répond. |

Ces résultats ne prouvent ni un login authentifié, ni un accès facture avec bearer. Aucun cookie ou jeton n'a été lu, fabriqué ou utilisé. La route Office `Invoices::list` est POST ; elle n'a pas été appelée. Aucun endpoint de mutation n'a été appelé.

### Flags

- Office `.env` : `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, `OFFICE_INVOICE_READ_API_ENABLED=true`.
- API `.env` : `INVOICE_DRAFT_RECETTE_GUARD_ENABLED`, `INVOICE_DRAFT_RECETTE_TENANT_ID`, `INVOICE_DRAFT_RECETTE_SUBJECT`, `INVOICE_WRITES_ENABLED` et `INVOICE_WRITE_SOURCES` absents. Le code du garde traite l'absence du flag comme OFF ; le gate global a un défaut OFF et la source effective est vide.
- Aucun override des noms de flags n'a été trouvé dans les configurations Apache, PHP-FPM ou les unités systemd inspectées. Les variables ciblées n'apparaissaient pas dans l'environnement déclaré des unités. Le dump de l'environnement d'un worker FPM n'a pas été accessible ; aucun secret n'a été exposé.
- Les valeurs n'ont pas été modifiées. Il n'y a pas de configuration privée d'identité/tenant permettant d'activer le garde.

La preuve antérieure `SCHEMA_024_VERIFIED=PASS` et `BACKUP_VERIFIED=PASS` est référencée depuis les rapports de déploiement. Ce lot n'a fait aucune requête SQL et n'a pas rejoué 024. `RESTORE_TESTED=NOT_RUN` reste inchangé.

## Revue du parcours Office et du garde API

Dans le code de la release Office :

- `DevsysInvoiceDraftService::form()` génère un contexte de création uniquement lorsque le flag API est actif ; ce contexte et les clés d'idempotence sont stockés côté session serveur.
- `saveDraft()` avec flag actif passe par `assertCreateContractReady()` et `createApiDraft()`. Les erreurs du SDK remontent au contrôleur ; il n'existe pas de repli vers `legacy->saveDraft()` dans cette branche API.
- **Avec le flag Office OFF, une création neuve sans contexte API peut appeler `legacy->saveDraft()` et écrire dans le stockage SQL historique.** Il est donc interdit de soumettre le formulaire de production pour tester le refus.
- Pour un brouillon API reconnu, PATCH exige la provenance `api/devsys`, le flag actif, l'éligibilité indiquée par l'API, un contexte de session serveur, ETag/révision et une clé idempotente stable. Les erreurs API ne déclenchent pas de SQL de secours.
- `issueDraft()` refuse une facture d'origine API avant l'émission SQL ; `deleteDraft()` refuse une facture API avant suppression SQL. Les capacités UI des factures API gardent `can_issue=false` et `can_delete=false`.
- Les sélecteurs tenant/issuer/numéro/source et les clés d'idempotence imposés par le navigateur sont rejetés par la validation du service.

Dans la release API, l'ordre des contrôles pour les mutations facture est : politique host/routage, authentification du bearer, scope `write:invoices`, accès facture et contexte tenant/source vérifiés côté serveur, garde de recette, gate global et enfin contrôleur. Le garde exige un principal `User`, le sujet serveur configuré, le `TenantId` effectif serveur correspondant et `invoice_source_app=devsys`; `issue` est refusée pour tous lorsque le garde est ON. Les GET liste/détail ne traversent pas le garde et conservent leurs contrôles lecture/tenant. Cette analyse est statique et s'appuie aussi sur les tests unitaires du garde ; aucun refus n'a été testé par une écriture HTTP réelle.

## Tests exécutés

Tests lancés avec le compte `devsys` dans les worktrees isolés ; aucun DSN de production n'a été fourni.

| Projet / commande | Résultat |
|---|---|
| API `composer test` | `PASS` — 269 tests, 876 assertions, 16 ignorés (15 intégration MySQL sans DSN explicitement isolé ; 1 test SQLite indisponible), 6 dépréciations PHPUnit. |
| Office `composer test` | `PASS` — 138 tests, 384 assertions. |
| Tests SQL | `NOT_RUN` — aucune base testée ; aucune DB connectée. |
| `npm run build` | `NOT_RUN` — aucun code Office/JS n'a été modifié dans ce lot. |
| `git diff --check` | `PASS` après ajout du rapport. |

Aucun code applicatif n'a été changé ; les chiffres ci-dessus sont les résultats réellement rejoués dans les clones, pas des tests de production.

## Conditions restant à remplir avant tout GO mutateur

1. Un opérateur réalise le parcours OIDC existant dans son navigateur et confirme `PASS/FAIL` sans transmettre cookie, bearer ou identité privée : dashboard, contexte utilisateur et tenant, scope requis, accès aux écrans de facturation et recherche client en lecture seule.
2. Dans cette session légitime, l'opérateur confirme qu'un client fictif déjà existant est disponible et autorisé pour le tenant courant. Aucun client ni enregistrement n'a été consulté ou créé par ce lot.
3. Les valeurs privées du garde (sujet utilisateur API et tenant serveur autorisés) doivent être configurées hors Git et validées par l'opérateur via un canal serveur privé. Elles sont actuellement absentes. Ne pas les envoyer dans le chat ou les rapports.
4. Avant tout changement de flags, un mécanisme d'expiration indépendant de la session Codex doit être préparé, testé et autorisé, avec une durée maximale de 15 minutes. Aucun timer/override n'a été créé dans ce lot.
5. L'opérateur doit confirmer qu'aucun autre consommateur ne dépend d'écritures pendant cette fenêtre, puis donner un GO mutateur séparé qui nomme les releases, principal/tenant/client validés hors rapport, jeu de données fictif exact, au plus un POST create et trois PATCH sur le même brouillon, fenêtre, changements de flags autorisés et procédure de fermeture.

La migration 024 n'est pas exécutée à nouveau. Le brouillon éventuel devra rester non émis et être conservé pour un nettoyage distinctement autorisé. Aucune opération d'émission/suppression ne fera partie de la recette.

## Procédure de vérification humaine (lecture seule)

Dans le navigateur habituel de l'opérateur, ouvrir Office et effectuer le login OIDC normal. Vérifier dashboard, liste factures, recherche de client et ouverture d'un formulaire **sans l'enregistrer**. Ne pas transmettre de captures affichant des données personnelles ; répondre seulement `PASS`/`FAIL` pour l'authentification, le tenant courant et la présence d'un client fictif déjà autorisé. Ne cliquer sur aucun bouton Enregistrer, Émettre ou Supprimer. Si aucun client de test n'existe, arrêter ; ne pas substituer un vrai client.

**Décision : `BLOCKED_AUTHENTICATED_SESSION`** — aucun GO mutateur ne peut être présenté tant que la session authentifiée et le client fictif ne sont pas confirmés, et que la configuration privée du garde et l'expiration indépendante ne sont pas prêtes.
