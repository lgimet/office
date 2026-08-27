# Première génération PDF de facture

## Source historique

Le PDF est généré uniquement pour les factures `issued` ou `cancelled`, à partir de la facture et de ses lignes persistées. `InvoicePdfService` ne consulte ni `company_settings`, ni le client local, ni `ClientsApi`, et ne recalcule ni les montants, ni les dates, ni la numérotation.

Les informations d’émetteur utilisent `issuer_*`, celles du client `client_*`, et le template est résolu par `InvoiceTemplateResolver::resolveForInvoice()` depuis `pdf_template_source` et `pdf_template_version`.

## Modèle et rendu

Le template dynamique est [pdf.twig](/home/laurent/Sites/devsys/office/src/Views/Invoices/pdf.twig). Il utilise un tableau HTML compatible mPDF, avec en-tête répétable, formatage français des dates et montants, notes publiques et coordonnées bancaires optionnelles. La note interne n’est jamais rendue.

Le fond `first-page.pdf` est utilisé sur la première page et `continuation.pdf` sur les pages suivantes. Le fallback éventuel de continuation reste celui du resolver. Aucun PDF final n’est écrit sur disque ni archivé.

## Route et réponse

`GET /Invoices/{id}/pdf` est authentifiée et réutilise `InvoiceService::view()`, donc l’isolation tenant et le refus des brouillons restent centralisés. La réponse binaire utilise `application/pdf` et une disposition `inline` avec un nom de fichier nettoyé. La vue de facture ajoute un lien PDF ouvrant un nouvel onglet.

## Sécurité

Les données sont échappées par Twig. Aucun champ d’émetteur, client, tenant ou template n’est accepté depuis la query string. Le répertoire temporaire mPDF est privé (`OFFICE_PDF_TEMP_DIR` ou le répertoire temporaire système), jamais `public/`.

## Tests

Les tests couvrent le rendu d’une facture émise avec accents, caractères spéciaux et retours à la ligne, le refus d’un brouillon et la sélection du template historique. Résultat : `composer test` — 109 tests, 211 assertions ; `npm run build` — OK ; `git diff --check` — OK.
