# OFFICE-INVOICE-PDF-API-1-ACTIVATION

Date : 25 novembre 2026  
Verdict : **`BLOCKED_DEPLOYMENT_METHOD`**

## Préflight constaté

- Sources fusionnées vérifiées : Office-Api `b7bcf3264d4f7a392d2ed87593a13da088203e16`, office `dfaa4355e254d29c4e0a0e321d1e2d24070ad2fc`, shared `93a0a8577094eb6f911cc54508eae14602399e15`. Le SDK `d5606571c3a4d8a9fd67216ed87b3870e9f1a61d` est inclus dans shared/main et reste la référence Composer du code Office.
- Releases servies, confirmées par les liens `current` et les DocumentRoots API/Office : `api-invoice-facturx2-20261124-7f88c7c-r1` et `office-invoice-facturx2-20261124-eb2f7aa`. Les deux restent inchangées.
- Volume `/home/devsys` : 8,4 Gio libres, inodes à 12 % utilisés.
- `devsys-backup.service` : dernier résultat `success`, code 0, fin le 25/11/2026 à 03:26 CET ; timer actif, prochaine échéance le 26/11 à 03:20 CET. Aucune restauration n'a été testée dans ce lot.
- Base `office` : MariaDB 10.9.4 ; registres et schémas des migrations 027 et 028 cohérents, aucune migration du code fusionné en attente. Leurs SHA-256 sont respectivement `db17f98228401a3ad5bf27ce76e59b2c5a5dc0169442eddaf5c76ac1e05e7be0` et `24d24bf602eed2de146eaf2da7fad97ce5afc3d8a9a84743cc1ae27d32efcd95`. Ces migrations avaient déjà été appliquées avant ce lot ; aucune opération SQL n'a été effectuée ici.
- Gates observés et conservés : écritures API ON, source `devsys`; Factur-X API et Office ON; lecture API Office et écriture brouillon via API ON. `INVOICE_PDF_ARCHIVE_DIR` et `INVOICE_PDF_WORK_DIR` sont absents de la configuration effective. Aucun répertoire de stockage PDF n'a été créé.

## Blocage de la bascule

Les scripts installés dans les répertoires `api-deploy` et `office-deploy` ne fournissent pas de méthode alternative documentée. Le helper API-only disponible dans la source fusionnée exige que les flags Factur-X soient OFF, que `INVOICE_WRITES_ENABLED` soit OFF et que `INVOICE_WRITE_SOURCES` soit vide. Cet état ne correspond pas aux gates actifs et ne peut pas être imposé dans ce lot. Le switch générique `deploy-vhost-release.py` cible les quatre vhosts API **et login** et exige également les écritures fermées. Il est donc hors périmètre.

Aucune bascule manuelle des vhosts ni copie fichier par fichier dans l'arbre servi n'a été improvisée. L'API et Office restent sur les releases existantes. Aucun rechargement Apache/FPM, changement de flag, migration ou opération sur une facture n'a eu lieu.

## Vérifications et limites

Les tests d'intégration code restent ceux du rapport de fusion : API 344 tests/1 472 assertions sur la base jetable, shared 57/239, Office 176/551 et build npm réussi. Ils n'ont pas été relancés, aucune source n'ayant été modifiée dans ce lot. Aucun candidat de release n'a été construit ; il n'y a donc ni checksum d'artefact candidat ni smoke test post-bascule.

Le prochain préalable unique est une méthode de bascule API puis Office, complète et réversible, qui conserve les gates actifs et ne touche ni `login.devsys.fr` ni un arbre servi fichier par fichier. Une fois cette méthode autorisée et disponible, configurer les deux chemins PDF privés, déployer l'API puis Office, et effectuer les GET de santé avant toute recette métier. Ce rapport n'autorise aucune de ces opérations.
