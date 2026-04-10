# Deploiement Local - Nema ERP

## Prerequis

- PHP 8.2+
- MySQL 8+
- Node.js 20+
- Composer fonctionnel
- XAMPP ou equivalent

## Checklist de deploiement

1. cloner le projet
2. copier `.env.example` vers `.env`
3. configurer la base MySQL
4. executer `composer install`
5. executer `php artisan key:generate`
6. executer `php artisan migrate --seed`
7. executer `npm install`
8. executer `npm run build`
9. verifier `php artisan test`
10. executer `powershell -ExecutionPolicy Bypass -File .\scripts\check-nema-erp.ps1`
11. lancer `powershell -ExecutionPolicy Bypass -File .\scripts\start-nema-erp.ps1`
12. si besoin, arreter le serveur avec `powershell -ExecutionPolicy Bypass -File .\scripts\stop-nema-erp.ps1`

## Checklist avant mise en service

- comptes de demo supprimes ou mots de passe changes
- `APP_DEBUG=false`
- sauvegarde testee
- restauration testee
- dossier `storage` accessible en ecriture
- fuseau `Africa/Bamako` verifie
- base MySQL dediee au projet
- assets Vite construits ou mode dev actif

## Commandes utiles

```powershell
C:\xampp\php\composer.bat install
C:\xampp\php\php.exe artisan migrate --seed
C:\xampp\php\php.exe artisan test
powershell -ExecutionPolicy Bypass -File .\scripts\check-nema-erp.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\start-nema-erp.ps1
npm run e2e:pos
```
