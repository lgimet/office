# OFFICE-INVOICE-ACTIVATE-API-1 — Office

Date : 2026-11-23 (Europe/Paris)

## Résultat

`API_MODE_ENABLED`. Le flag Office et les gates API requis sont activés ; l'interface utilise désormais le chemin API pour les nouvelles factures. Aucun formulaire n'a été soumis et aucune facture n'a été créée/modifiée par Codex.

## Releases et configuration

- Office active : `office-invoice-mvp1-20261123-8150e7f`, SHA source `8150e7fa4956f8bdc85ef337950b8100c5c20b30`.
- API/login active : `api-invoice-mvp1-20261123-4f5baec`, SHA source `4f5baec6698b8fdb5e4da1a3017c118f3cadf66e`.
- `current` n'a pas changé. Le `.env` Office actif résout vers `/home/devsys/office/.env`, `devsys:www-data`, mode `0640`, sans ACL nommée observée ; mode, propriétaire et groupe conservés.
- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=true`, une seule affectation.
- `OFFICE_OIDC_SCOPES` a une affectation et inclut `write:invoices`; le client configuré correspond à Office. Le scope effectif dans le bearer utilisateur n'a pas été vérifié et n'est pas revendiqué.
- API `INVOICE_WRITES_ENABLED=true`, `INVOICE_WRITE_SOURCES=devsys`. Garde de recette et diagnostics restent absents/OFF.
- Aucune surcharge FPM des clés concernées observée. Office charge Dotenv au bootstrap ; vérification PHP Dotenv `OFFICE_DOTENV=PASS`. Aucun reload/restart n'a été effectué.

## Sauvegarde et retour arrière

- Sauvegarde ciblée : `/home/devsys/api-deploy/backups/office-invoice-activate-api-20261123T180005+0100/targeted-flags-before.json`, mode `0600`, répertoire privé `0700`. Elle consigne l'état antérieur des seules clés de mode.
- État Office avant : `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`. Pour revenir en arrière, régler cette seule clé à `false` dans `/home/devsys/office/.env`; API gate OFF et allowlist vide doivent être appliqués en premier si le retour arrière ferme toute l'écriture.
- Le flag Office sélectionne `DevsysInvoiceDraftService → shared/InvoicesApi → api.devsys.fr` pour les nouvelles créations. Tant qu'il est `true`, un échec de l'API ne bascule pas une facture API sur SQL historique. Une fois ce flag remis OFF, ne pas créer de nouvelle facture via le formulaire : la voie historique SQL pourrait être utilisée.

## Contrôles

- API health 200, OIDC discovery 200 et collection factures sans bearer 401 ont été recontrôlés après activation. Aucun GET Office n’a été répété pour ce changement de configuration uniquement.
- Aucun POST/PATCH/issue/delete métier ; aucun client ni facture créé ; aucune migration/SQL.
- Tests et build : `NOT_APPLICABLE (configuration only)`.

## Émission

Le gate API est commun. Avec la source `devsys` autorisée et garde de recette absent/OFF, les contrôles normaux peuvent autoriser aussi une requête `issue` émise avec un principal et un scope valides. Le fait que l'interface Office ne présente pas encore l'action n'est pas une interdiction API. Aucune émission n'a été tentée ; toute émission reste à traiter dans le lot ultérieur.

L'opérateur peut maintenant effectuer lui-même dans Office une création fictive puis modifier ce brouillon. La réussite du bearer/scopes n'est pas affirmée avant son retour fonctionnel.
