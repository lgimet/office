# OFFICE-INVOICE-FACTURX-2-ISSUE-POLICY — Office

Statut : `IMPLEMENTED` sur branche de travail, non fusionné et non déployé.

Références rafraîchies : Office `origin/main` `1e78c6086eba45eddff270f87eb24e0addc799bf`; shared `origin/main` `7a1644c75c918140a28651d527ee3ab99b96a943`; Office-Api `origin/main` `7f88c7ca5de84313aae262c7483ed7f49d9be4f4`. Branche Office `feature/office-invoice-facturx-2-issue-policy`. SDK embarqué dans le worktree : commit `shared` `f1215a8f0a32c660115e64de75504018c930053e` (verrou exact dans `composer.lock`).

## Comportement livré

Le formulaire de brouillon API propose le choix explicite « Factur-X · EN 16931 » ou « Aucune exigence Factur-X ». Le choix est transmis via `DevsysInvoiceDraftService` et le SDK `shared`; la valeur affichée au retour est lue depuis les métadonnées de l'API. Une absence de champ lors d'une modification conserve le profil déjà lu ; une valeur vide soumise explicitement le retire. Le tenant et l'émetteur ne sont jamais fournis par ce sélecteur.

À la prochaine émission d'un brouillon opté, l'API validera le candidat exact avant attribution du numéro. Un refus est présenté en français par catégories de champs autorisées (coordonnées, mentions, lignes, paiement, totaux), sans payload ni valeur personnelle ; un refus pré-numérotation indique qu'aucun numéro n'a été attribué. Les erreurs inconnues/invalides utilisent un message sûr. Le message de génération d'une facture déjà émise reste distinct.

Le profil est facultatif : les brouillons sans sélection gardent le comportement métier actuel. Les composants de formulaire existants sont réutilisés ; le PDF historique et l'éditeur des factures émises ne changent pas.

## Contrats et validations

L'Office-Api ajoute le champ nullable `document_profile` avec la seule valeur `facturx_en16931` dans une migration additive 027. Le SDK partagé est verrouillé sur une référence Git exacte ; l'API seule résout le tenant, la source autorisée et le mapping tenant→émetteur. Aucun SQL Office ni HTTP direct Office→API n'est ajouté.

## Vérifications

- `composer test` : PASS, 174 tests, 542 assertions.
- `npm run build` : PASS après `npm ci` depuis le lock (la première tentative avant installation locale s'était arrêtée car `sass` manquait).
- `git diff --check` : PASS.
- `composer check-platform-reqs --no-dev` : PASS (PHP 8.4.24 et extensions requises présentes).
- `composer validate --strict` : échec de règles de publication déjà manquantes dans le projet (`description`, `license`), sans lien avec cette modification ; le manifeste reste utilisable par Composer.
- `composer audit --locked --no-dev` : signale 9 avis sur la version Twig déjà verrouillée (`twig/twig`, 3.30.0). Le SDK ne change pas Twig ; cette mise à jour de dépendance n'est pas incluse dans le lot.

Le SDK `devsys/shared` est verrouillé sur la référence immuable `f1215a8f0a32c660115e64de75504018c930053e`, vérifiée dans `vendor`. L'update Composer a ciblé exclusivement `devsys/shared` ; aucun `composer update` global n'a été exécuté.

## Non effectué

Aucune migration de production, émission, génération Factur-X, modification d'une facture émise, changement de flag, déploiement ou action sur les factures `2026-0001`, `2026-0002` et `2026-0003`. La validation du profil confirme uniquement le sous-ensemble métier du générateur existant ; elle ne déclare pas une conformité réglementaire française exhaustive.
