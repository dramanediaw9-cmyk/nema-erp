# Checklist Mise en Service - Nema ERP

## Avant ouverture a un client pilote

- verifier que `APP_ENV` et `APP_DEBUG` sont corrects
- executer `php artisan test`
- lancer une sauvegarde et tester une restauration locale
- verifier les comptes utilisateurs et changer les mots de passe de demo
- controler les sequences de documents dans `Parametres`
- verifier l entreprise active, les agences et les comptes de tresorerie
- lancer un import de demonstration si besoin
- valider une vente, un achat, une depense et un paiement dans le navigateur
- verifier les impressions et exports CSV
- verifier que la periode comptable du mois courant est bien ouverte

## Commandes recommandees

```powershell
C:\xampp\php\php.exe artisan test
powershell -ExecutionPolicy Bypass -File .\scripts\check-nema-erp.ps1 -RequireProductionSettings
powershell -ExecutionPolicy Bypass -File .\scripts\backup-nema-erp.ps1
```

## Smoke test navigateur

1. se connecter avec un compte administrateur
2. ouvrir `Dashboard` et `Approbations`
3. creer une facture de vente et verifier l etat du workflow
4. creer un achat et verifier l entree de stock
5. creer une depense et verifier le suivi comptable
6. ouvrir `Paiements` puis `Comptabilite > Journaux`
7. tester l impression d une facture
8. verifier les alertes internes et les notifications sortantes
