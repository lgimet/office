# Migration du système de formulaires Office

## Résumé

Le socle de formulaires Office a été rapproché de la référence LivePulse : labels fixes au-dessus des contrôles, champs plus compacts, grille responsive, sections légères et états de validation discrets. Les hooks JavaScript existants ont été conservés.

## État initial observé

Les vues `Company`, `Client`, `Clients` et `Invoices` utilisaient `.form-group` et `.form-field`, avec des labels flottants pilotés par CSS. Les contrôles personnalisés dépendaient de `.select`, `.custom-multi-select`, `[data-bank]` et `[data-mask]`. `FormValidator` injecte ses messages dans `.form-error-inline`; `FormDirtyTracker` sérialise les formulaires sans dépendre du style.

## Fichiers LivePulse utilisés comme référence

`resources/_controls.scss` et `resources/controls-demo.html` ont été inspectés pour la grille 12 colonnes, les labels fixes, les hauteurs compactes, les états d’erreur et les chips. `resources/controls.css` reste une référence de comparaison uniquement. Aucune classe `.lp-*` ni variable `--lp-*` n’a été importée dans Office.

## Fichiers Office modifiés

- `public/assets/css/scss/components/_form.scss` : nouveau socle natif Office.
- `public/assets/css/scss/components/_select.scss`, `_multiselect.scss` et `_bank.scss` : contrôles compacts harmonisés.
- `public/assets/css/scss/components/_invoice.scss` et `dashboard.scss` : style dédié à l’éditeur de facture dense.
- `src/Views/Client/form.twig` : labels bancaires fixes, sans label flottant spécifique.
- `public/assets/css/dashboard.css` et `public/assets/css/login.css` : générés par le build Sass existant.

## Nouveau système de formulaires

`.form-field` est conservé comme hook JavaScript mais devient une pile label → contrôle → aide/erreur. `.form-group` fournit une section légère; `.form-grid` et les classes `form-col-3`, `form-col-4`, `form-col-6`, `form-col-8`, `form-col-12` fournissent une grille responsive. Les contrôles utilisent une hauteur de 36 px, des bordures fines, un radius modéré et un focus `--focus-ring` discret.

## Tokens utilisés

La migration utilise les tokens Office existants : `--primary`, `--surface`, `--surface-base`, `--surface-low`, `--surface-high`, `--on-surface`, `--on-surface-variant`, `--outline`, `--outline-variant`, `--error`, `--focus-ring`, `--motion-fast` et les tokens de typographie. Aucun token LivePulse n’est ajouté.

## Compatibilité conservée

### CustomSelect

`.select`, son input hidden, son champ de recherche, ses options clavier et son dropdown sont conservés. Seule l’apparence est compactée.

### CustomMultiSelect

`.custom-multi-select`, `.cms-label`, `.cms-dropdown`, `.cms-option` et les valeurs hidden sont conservés. Les badges sont plus bas et peuvent toujours s’afficher sur plusieurs lignes.

### BankField

`[data-bank]`, `.bank-iban`, `.bank-bic`, les inputs hidden, le formatage IBAN/BIC et les statuts de validation sont conservés. Les labels sont désormais fixes au-dessus des champs.

### InputMask

Les attributs `[data-mask]` restent inchangés; le formatage téléphone et les autres masques ne sont pas déplacés.

### FormValidator

La validation native, les classes `is-invalid`/`is-valid`, l’injection `.form-error-inline`, le scroll vers le premier champ invalide et l’animation restent inchangés. Le style des messages est simplement plus compact.

### FormDirtyTracker

Le tracker continue d’écouter `input`/`change` et de sérialiser les formulaires. Aucun hook de dirty tracking n’a été modifié.

## Migration Company

Les cinq `fieldset.form-group` existants deviennent des sections légères avec grille responsive. Les adresses restent larges lorsque nécessaire; les champs courts sont disposés en colonnes sur desktop et repassent en une colonne sur mobile.

## Migration Client

Les champs société, contact, description, pays, modules, notifications, IBAN et BIC conservent leurs composants. Les labels sont fixes; les modules restent un multiselect à chips, la notification conserve sa checkbox accessible et BankField garde son formatage/validation.

## Migration Facture

Les informations générales et les notes utilisent le nouveau style des `.form-field`. L’éditeur de lignes reste volontairement dense : ses inputs, selects, remises, boutons et totaux sont harmonisés sans changer la structure ni la logique de calcul. Sur petit écran, l’éditeur conserve son comportement horizontal spécialisé afin d’éviter de transformer chaque ligne en formulaire vertical.

## Responsive

La grille passe de 12 colonnes à des demi-largeurs sous 980 px, puis à une colonne sous 640 px. L’éditeur de facture passe son récapitulatif sous les lignes sous 1100 px et conserve un espace de travail compact pour les lignes sur tablette/mobile.

## Accessibilité

Les associations `label`/`for` existantes sont conservées, les labels restent visibles, le focus clavier utilise `--focus-ring`, les erreurs restent textuelles et les checkboxes conservent leur zone de clic et leur indication de focus. Les états ne reposent pas uniquement sur la couleur.

## Nettoyage SCSS réalisé

Les règles de label flottant de `_form.scss` et de BankField ont été remplacées. Les composants spécialisés Select, MultiSelect et BankField restent présents; aucun nettoyage agressif de règles dont l’usage n’a pas été confirmé n’a été effectué.

## Tests automatisés exécutés

`npm run build` compile sans erreur le dashboard et le login avec Sass 1.54.9. `composer test` passe avec 83 tests et 134 assertions; aucune logique métier ni authentification n’a été modifiée par cette migration.

## Tests manuels réalisés

Une vérification statique des vues et des hooks JavaScript a été réalisée. Les tests navigateur détaillés Company, Client et Facture aux résolutions 1366×768, 1280×800, 768×1024 et 390×844 restent à exécuter dans l’environnement d’intégration.

## Résultats

Le système dispose désormais d’une base native Office réutilisable, plus dense et sans dépendance aux classes LivePulse. Les contrôles métier existants restent branchés sur leurs sélecteurs historiques.

## Régressions éventuelles

Le cas des lignes de facture conserve un espace de travail horizontal spécialisé sur petit écran; c’est un compromis volontaire pour préserver la lisibilité et les calculs de l’éditeur.

## Points restant à traiter

Effectuer le test navigateur manuel complet des formulaires et vérifier les états réels de CustomSelect, CustomMultiSelect et BankField sur mobile avec les données de l’environnement.

## Suggestions UI pour une étape suivante

Ajouter des tests visuels automatisés et, si nécessaire, introduire des classes explicites `form-section`/`form-col-*` dans les futurs modules plutôt que d’étendre les sélecteurs historiques.
