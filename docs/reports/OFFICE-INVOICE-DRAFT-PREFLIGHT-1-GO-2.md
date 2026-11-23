# OFFICE-INVOICE-DRAFT-PREFLIGHT-1-GO-2 — Office bridge

Date : 2026-11-23. Statut : `READY_FOR_DIAGNOSTIC_DEPLOY_GO` pour revue; aucune preuve authentifiée réelle n'a été obtenue.

## Base, release et SDK

- Office worktree basé sur le rapport GO-1 `work/office-invoice-draft-preflight-1` SHA `75776e6662962505cd78e0904c5386fd5d167023`; code GO-2 `06aab44ae40723b0fcad4951cdbb97e5d2d527ba`, lignée issue du parcours fonctionnel 1.2.
- Release active résolue en lecture seule : `office-invoice-web-20261123-64b6b29-0661b92`.
- Le SDK candidat est `devsys/shared` commit `b845450b2d4004fa31a5e3a540184fff42c7ebba` sur une branche dérivée de `feature/office-api-business-reference-1.1`; `composer.lock` est épinglé à ce SHA exact (SHA-256 `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`). Le runtime actif conserve son paquet existant tant qu'une release Office n'est pas approuvée et déployée.

## Changement

Ajout d'une action POST `Invoices/recipePreflight`, marquée AuthRequired et non `api=true`; le routeur existant applique donc CSRF avant le contrôleur. Le contrôleur ignore le contenu fourni. Le service exige `OFFICE_INVOICE_PREFLIGHT_ENABLED=true` et un sujet utilisateur `user:<UUID>` qui correspond à l'identité serveur de la session à une allowlist privée `OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT`. Le bearer est obtenu par le chemin SDK partagé existant, qui utilise `OfficeAccessTokenProvider`; il n'est ni accepté du navigateur ni journalisé. L'API reste seule autorité pour `write:invoices`, le tenant effectif et la source. Résultat redacted seulement.

Aucune modification de `DevsysInvoiceDraftService` ou du chemin SQL historique. Aucune soumission de formulaire, écriture, lecture de session globale, configuration ou déploiement n'a été faite.

## Tests et limites

- `composer test` : PASS, 143 tests / 397 assertions, y compris l'action et le refus CSRF.
- `composer validate --no-check-publish`, `composer check-platform-reqs --no-dev` : PASS.
- PHP lint et `git diff --check` : PASS.
- `npm run build` : N/A, aucun frontend modifié.
- Diagnostic depuis une session authentifiée réelle : `NOT_RUN`; le scope, sujet et tenant API réels ne sont pas attestés. Pas d'appel HTTP production.

Les flags d'écriture restent inchangés et OFF selon les rapports précédents; ils ne sont pas lus depuis des secrets runtime et n'ont pas été activés. Aucun nouveau GO mutateur n'est demandé ici.

Prochaine étape : GO distinct de déploiement diagnostic pour des releases API/SDK/Office immuables, avec variables privées configurées hors Git; appel POST CSRF depuis la session légitime; retrait/désactivation diagnostique après preuve. La recette d'écriture reste séparée.
