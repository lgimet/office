# OFFICE-INVOICE-DRAFT-WRITE-1

**Date:** 2026-11-23 (Europe/Paris)  
**Résultat phase A:** `BLOCKED_SERVER_IDENTITY_TENANT`

**Phase mutatrice:** non exécutée ; aucun GO mutateur reçu.

## Résumé

Les releases Office et API attendues sont toujours actives. Les contrôles GET anonymes répondent conformément aux protections attendues. Les tests isolés API et Office passent. Aucun formulaire n'a été soumis, aucun POST/PATCH/issue/delete n'a été envoyé, aucun accès SQL ni changement de configuration n'a été réalisé.

L'opérateur a depuis déclaré la recette manuelle authentifiée réussie (OIDC, tableau de bord, liste, recherche de clients et client fictif autorisé). Cette déclaration est consignée ci-dessous comme telle ; elle n'est pas une capture HTTP instrumentée ni une preuve du principal/tenant effectivement présentés au middleware API. Les valeurs privées tenant/sujet du garde sont absentes et aucun mécanisme indépendant d'expiration de la fenêtre de flags n'est armé. Aucun secret, sujet ou identifiant tenant n'est consigné ici.

## Références et runtime constatés

| Composant | Référence | État observé |
|---|---|---|
| Office, rapport de recette antérieur | `work/office-invoice-draft-recette-1` `162480c4f8ea04bf76825c8c4cfd73a047ba8978` | Rapport `READ_ONLY_PARTIAL`; depuis ce rapport, une recette manuelle authentifiée a été déclarée PASS par l'opérateur. |
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

1. **Identité serveur non résolue :** la déclaration PASS de l'opérateur ne donne pas de liaison vérifiable entre sa session Office et les claims effectifs que l'API verra (`principal.subject`, `effective_tenant_id`). La release Office expose l'identité uniquement dans le contexte de session PHP serveur ; aucun identifiant de session n'a été fourni au mécanisme d'exécution et aucune interface serveur existante ne permet de lire ces claims sans session. Les fichiers de session n'ont pas été parcourus. Il faut une validation serveur privée depuis la session légitime ou une procédure opérateur existante produisant ces deux valeurs pour dépôt privé, sans les publier dans le chat/rapport.
2. **Configuration privée non préparée :** les clés du garde restent absentes du `.env` API (défaut OFF). Sans sujet et tenant validés selon le point 1, inscrire une configuration autorisée serait arbitraire. Aucun fichier de configuration n'a été modifié.
3. **Fermeture automatique non prête :** aucun timer/service d'expiration indépendant n'est installé, armé ou testé sur l'hôte. Un test de fermeture d'un fichier `.env` temporaire ne prouverait pas que le runtime réel API et Office se referme sous systemd. Il faut valider puis tester un mécanisme système qui force les trois contrôles à OFF au plus tard après 15 minutes, sans reload du pool FPM partagé ou en documentant précisément l'action nécessaire.
4. **Scope de mutation non attesté :** la déclaration opérateur confirme le parcours de lecture et le client fictif, mais pas l'obtention effective du scope API `write:invoices` pour cette identité, ni le résultat de l'isolation tenant/source au runtime. Ne pas modifier les scopes pour contourner cette inconnue.
5. L'absence de consommateurs ayant besoin d'écriture pendant la fenêtre n'est pas confirmée par une déclaration dédiée. Le GO mutateur séparé devra couvrir ce risque multi-consommateur, les releases, un seul POST et au plus trois PATCH du même brouillon, les flags autorisés, la fenêtre et le maintien du brouillon.

La migration 024 n'est pas exécutée à nouveau. Le brouillon éventuel devra rester non émis et être conservé pour un nettoyage distinctement autorisé. Aucune opération d'émission/suppression ne fera partie de la recette.

## Déclaration opérateur — recette manuelle lecture seule

Déclaration reçue de l'opérateur : connexion OIDC `PASS`, tableau de bord `PASS`, liste des factures `PASS`, recherche de clients `PASS`, client fictif existant et autorisé `PASS`. L'opérateur indique qu'aucune écriture métier n'a été effectuée. Ce constat est une **recette manuelle déclarée**, pas une preuve HTTP instrumentée ; aucun identifiant de client, utilisateur ou tenant n'est reproduit.

**Décision : `BLOCKED_SERVER_IDENTITY_TENANT`** — l'authentification et le client fictif sont déclarés PASS, mais le sujet/tenant serveur n'ont pas été résolus ni validés ; la configuration privée ne peut donc pas être préparée. L'expiration automatique n'est pas prête/testée et le scope d'écriture n'est pas attesté. Les flags demeurent OFF ; aucun GO mutateur n'est accordé ou inféré.
