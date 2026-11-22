# OFFICE-INV-RECETTE-1.2 — Parcours Office des brouillons API DevSys

**Date :** 22 novembre 2026 (Europe/Paris)  
**Branche :** `feature/office-inv-recette-1.2`  
**Base :** `work/office-deploy-php84-2.2` (`1333e34aac9f1738afaf795efdcb77044be163e9`)  
**Commit fonctionnel :** `19319b8c7d68084d8a0b625ae599fb40fe5ff49e`  
**Décision : `READY_FOR_REVIEW` — code et tests préparés, non déployés. Les écritures restent OFF.**

## Sources relues et choix de base

| Dépôt / branche distante | SHA vérifié |
|---|---|
| Office `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` |
| Office `work/office-deploy-php84-2.2` | `1333e34aac9f1738afaf795efdcb77044be163e9` |
| Office-Api `main` | `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf` |
| Office-Api `feature/office-api-invoice-numbering-1.1` | `c9df8851d7d1f6d96ac185101b478d2e63862148` |
| shared `main` | `83e5b7da32ae20039218586e18dc93ee9ad95852` |
| shared `feature/office-api-business-reference-1.1` | `0661b92b914c28c41f45f5bd19bb9a860c232676` |

La branche Office 2.2 contient déjà les contrôleurs, services, contexte de retry, éditeur, hooks et protections API/SQL des brouillons. Elle est 44 fichiers / environ 3 700 lignes au-delà de `office/main`, notamment les lots UI-INV-2.1 et PHP 8.4, ainsi que des rapports, ressources, packaging et ajustements sans rapport direct avec ce correctif. Pour que la revue reste circonscrite, le changement est basé sur 2.2 et la PR proposée cible cette branche fonctionnelle ; une PR de cette branche directement vers `main` réintroduirait l'ensemble de ces écarts et n'est pas proposée ici.

Le rapport Office 2.2 indique que l'application a été déployée en lecture seule sur le vhost Office. L'exploitant a déclaré avoir effectué six vérifications manuelles (OIDC, tableau de bord et consultation des factures/client/montants). Elles sont consignées comme déclaration opérateur, sans les présenter comme des contrôles HTTP instrumentés ni comme une autorisation d'écriture. Aucun accès HTTP authentifié n'a été exécuté pour ce lot.

## Parcours réalisé dans le code

- **Création :** le formulaire API réutilise le sélecteur de clients actifs existant, les champs de dates, conditions, lignes et remises. `DevsysInvoiceDraftService` construit `CreateInvoiceDraftRequest` par le SDK avec `source_app=devsys`, `source_event_id` et clé d'idempotence issus du contexte serveur. Une référence vide est omise par le DTO. La création API n'a pas de repli SQL en cas d'erreur.
- **Retry et double clic :** le verrou UI déjà présent empêche deux soumissions simultanées. Le contexte de session conserve la même clé, le même événement et le hash du payload ; un retry incertain réutilise les valeurs initiales. Après réponse, l'Office recharge le détail API et actualise la liste.
- **Consultation / réouverture :** provenance, UUID client, ETag, révision, lignes et totaux viennent de `InvoiceDetails`. La référence métier est préremplie depuis le détail. L'aperçu recalculé en JavaScript reste indicatif ; la vue recharge et affiche les totaux lus depuis l'API.
- **Modification :** un brouillon API `devsys` éditable utilise le contexte serveur d'ouverture et l'ETag capturé ; le service appelle `InvoicesApi::updateDraft()` par l'adapter existant. Une réponse `412` conserve le formulaire et indique comment recharger ; les réponses API `401/403/404/409/412/428/422` et réseau restent des messages Office sans écriture SQL de secours.
- **Référence métier :** le champ n'apparaît que dans le formulaire API, avec libellé séparé du numéro légal. L'intention initiale d'un brouillon existant est `preserve` : la clé est omise, même si le champ est affiché. Une saisie explicite envoie une référence `DEV-…`; la case « Effacer la référence enregistrée » envoie explicitement `null`. Effacer le texte au clavier sans cocher cette case bloque la soumission. Si l'intention `preserve` accompagne une valeur différente de celle relue côté API, le serveur Office refuse la sauvegarde au lieu d'ignorer la saisie.
- **Date et échéance :** les dates passées/futures restent possibles pour les brouillons. Office refuse `due_date < issue_date` sans recalcul silencieux de l'échéance.
- **Actions :** les routes Office répètent les contrôles de provenance. Les brouillons API n'ont jamais d'action `issue` ou `delete`, même si un POST Office est forgé ; seules les factures historiques restent sur les actions SQL historiques. Pour la liste lue depuis l'API, Office demande les capacités serveur de chaque brouillon de la page afin que `DynamicTable` masque l'édition/suppression inconnue ou refusée. Une erreur de provenance masque toutes les actions de mutation. Cette vérification entraîne un GET de détail par brouillon de la page ; une inclusion de provenance/capacités dans la réponse de liste API permettrait d'éviter ce coût dans un lot ultérieur.

Le corps Office refuse `tenant_id`, `tenant_uuid`, `issuer_id`, `invoice_number`, `source_app`, `source_event_id` et `idempotency_key` fournis par le navigateur. Le routeur Office ne passe pas la query ni les en-têtes arbitraires aux actions POST ; le tenant/source API restent issus du contexte serveur OIDC et non du formulaire. Les clients sont choisis par la recherche Devsys déjà authentifiée côté serveur. Aucune URL HTTP API ni aucun SQL facture API n'est ajouté dans l'interface.

## Fichiers modifiés

- `src/Services/DevsysInvoiceDraftService.php` — contrat SDK vérifié par capacités, référence en create/update/read, intention de conservation/remplacement/effacement, validation DEV, échéance, rejet des champs de sécurité.
- `src/Controllers/Invoices.php` — capacités de mutation par provenance ajoutées aux brouillons de la liste API ; échec fail-closed pour les actions.
- `src/Views/Invoices/form.twig` — champ compact de référence et contrôle d'effacement explicite, seulement en mode API.
- `public/assets/js/Object/Invoices.js` — état d'intention du champ, validation UI, filtrage des actions selon les capacités serveur.
- `tests/Unit/DevsysInvoiceDraftServiceTest.php` — create sans référence / avec référence, préremplissage, omission qui préserve, valeur, effacement explicite, source étrangère, clés de retry et champs de sécurité forgés.
- `tests/Unit/InvoicesDataSwitchTest.php` — vérifie le filtrage des actions pour brouillon API et historique, sans liste SQL de secours.
- `docs/reports/OFFICE-INV-RECETTE-1.2.md` — présent rapport.

Aucun changement de `shared`, Office-Api, migration, `composer.json`, configuration de service, client OAuth/OIDC ou scope n'est inclus.

## Compatibilité API / SDK et packaging observé

Le code est compilé et testé contre `shared` `feature/office-api-business-reference-1.1` (`0661b92b…`) dans un workspace temporaire frère, exactement comme le dépôt `path ../shared` du manifeste. Dans ce seul environnement de test, `vendor/devsys/shared` est un symlink vers cette copie, et `UpdateInvoiceDraftRequest::clearingBusinessReference()` est disponible. **Cela ne prouve pas la version du package installé sur le runtime Office.**

Le `composer.json` Office exige encore `devsys/shared: dev-main@dev` via un dépôt `path` `../shared` avec symlink ; `composer.lock` est ignoré par Git et absent du commit source. Le `composer install` du clone a donc dû résoudre les contraintes pour les tests et a généré un lock local ignoré. Il n'est pas présenté comme une installation reproductible. Le runtime doit recevoir une version publiée/immuable du SDK 1.1, ou un artefact versionné avec lock et SHA exact ; il ne faut pas pointer le runtime vers une branche ou un symlink mutable. L'API runtime et sa migration 024 ne sont pas vérifiées par ce lot.

Le code échoue explicitement et sans mutation si le SDK chargé ne peut pas transmettre la référence en création ou si le DTO PATCH n'offre pas le mécanisme d'effacement explicite. Le flag d'exemple `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED` demeure `false`; aucune variable runtime n'a été chargée ou changée. La présence de l'API 1.1, l'application de la migration 024, le mapping serveur d'émetteur et le garde recette API ne sont pas affirmés.

## Vérifications réellement exécutées

Environnement isolé : PHP CLI 8.4.24, Composer 2.10.2, Node 22.23.0, npm 12.1.0 ; aucun accès DB, HTTP réel ou secret de production.

| Contrôle | Résultat |
|---|---|
| `composer validate --no-check-publish` | PASS, avertissement existant : `license` absent |
| `composer check-platform-reqs` | PASS, PHP 8.4.24 et extensions requises disponibles |
| `composer test` | PASS — 138 tests, 384 assertions |
| `php -l` | PASS — service, contrôleur et deux tests touchés |
| `git diff --check` | PASS après les derniers changements |
| `node --check public/assets/js/Object/Invoices.js` | PASS |
| `npm ci --no-audit --no-fund` | PASS — 17 paquets installés dans le clone isolé |
| `npm run build` | PASS — build Sass du dépôt ; aucun actif du serveur n'a été touché |
| Rendu Twig du formulaire API | PASS — rendu non mutateur avec fixture synthétique minimale |
| Tests navigateur/OIDC, HTTP, SQL ou concurrence | NOT_RUN — hors de ce lot et sans mutation simulée contre une infrastructure active |

Les tests sont des PHPUnit avec mocks/services Office. Ils n'affirment pas qu'une route ou une écriture est activée dans l'API. Les critères de transaction, idempotence serveur, ETag, scope, émission et persistance restent dépendants de l'API 1.1 et de sa migration 024 ; aucune base MariaDB n'a été utilisée.

## État des écritures et actions exclues

`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED` garde sa valeur par défaut `false` dans `.env.example`. Aucun `.env` runtime n'a été lu ou modifié et aucun flag n'a été activé. Aucune création/modification API, émission, suppression, numérotation, mutation SQL, migration, changement de scope, rechargement ou déploiement n'a eu lieu. Aucune facture test ou réelle, aucun client, e-mail, PDF final, paiement, webhook ou export n'a été généré.

## Intégration proposée et prérequis de déploiement

1. Revoir puis intégrer API `feature/office-api-invoice-numbering-1.1`; inclure la migration 024 dans une release API approuvée et vérifier sa compatibilité avant toute écriture.
2. Publier shared 1.1 sous version/tag immuable puis construire Office avec ce SHA fixé et un `composer.lock` versionné dans une stratégie de release approuvée. Le runtime doit passer le contrôle de capacités DTO avant toute ouverture de flag.
3. Réconcilier le code Office 2.2 dans le flux Git principal par PR séparée ; ne pas prendre `office/main` seul comme base de ce correctif tant que le parcours UI-INV-2.1 n'y est pas intégré.
4. Avant toute activation future : vérifier la migration 024 et le mapping tenant→issuer côté serveur, le garde API tenant/principal en mode OFF prêt et testé, les droits OIDC de l'opérateur, le contrôle des consommateurs et effets externes, la sauvegarde/récupération et les deux flags OFF effectifs. Toute future recette mutatrice requiert un GO distinct et exclut issue/delete.

**PR ciblée proposée :** branche source `feature/office-inv-recette-1.2`, base `work/office-deploy-php84-2.2`. Ne pas créer de PR directe vers `main` qui inclurait les 44 fichiers de travaux antérieurs. Aucun merge ou déploiement n'est compris dans ce rapport.
