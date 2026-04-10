# Nema ERP

Nema ERP est une base Laravel modulaire pour demarrer un ERP destine aux PME maliennes. Cette fondation couvre desormais :

- authentification securisee par session
- roles et permissions maison
- gestion des entreprises
- gestion des agences
- gestion des utilisateurs
- parametres societe et sequences de documents
- workflow d approbation configurable
- portail d approbation
- alertes internes et notifications sortantes
- clients et fournisseurs
- categories produits
- catalogue produits
- stock par agence et mouvements
- factures de vente
- factures fournisseurs
- comptes de tresorerie
- encaissements clients
- reglements fournisseurs
- categories de depenses
- depenses simples
- comptabilite de base avec plan comptable, journaux et balance
- dashboard administrateur enrichi
- imports CSV et impressions
- journaux d'activite
- seeders de demonstration

## Stack

- Laravel 12
- MySQL
- Blade
- Vite pour les assets
- PHP 8.2+

## Guides utiles

- [Usage rapide](docs/USAGE-RAPIDE.md)
- [Tests manuels](docs/TESTS-MANUELS.md)
- [Deploiement local](docs/DEPLOIEMENT-LOCAL.md)
- [Publication Laravel Cloud](docs/PUBLICATION-LARAVEL-CLOUD.md)
- [Sauvegarde et restauration](docs/SAUVEGARDE-RESTAURATION.md)
- [Checklist mise en service](docs/CHECKLIST-MISE-EN-SERVICE.md)

## Structure du projet

```text
app/
  Modules/
    Core/
      Auth/
      Access/
      Company/
      Branch/
      Dashboard/
      Audit/
      Approvals/
      Notifications/
    Partners/
    Catalog/
    Inventory/
    Sales/
    Purchases/
    Treasury/
    Expenses/
    Accounting/
resources/views/
  layouts/
  partials/
  auth/
  dashboard/
  approvals/
  notifications/
  companies/
  branches/
  users/
  roles/
  settings/
  customers/
  suppliers/
  categories/
  products/
  stock/
  sales/
  purchases/
  cash-accounts/
  payments/
  expense-categories/
  expenses/
  accounting/
  activity-logs/
routes/modules/
database/migrations/
database/seeders/
docs/
scripts/
```

## Prerequis

- PHP 8.2 ou plus
- Composer
- Node.js 20+ ou equivalent
- MySQL 8+

## Installation / execution

1. Copier l'environnement si necessaire :

```powershell
Copy-Item .env.example .env
```

2. Verifier la base MySQL dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nema_erp
DB_USERNAME=root
DB_PASSWORD=
```

3. Installer les dependances PHP :

```powershell
C:\xampp\php\composer.bat install
```

4. Generer la cle d'application :

```powershell
C:\xampp\php\php.exe artisan key:generate
```

5. Executer les migrations et seeders :

```powershell
C:\xampp\php\php.exe artisan migrate --seed
```

6. Publier le lien public pour les fichiers uploades :

```powershell
C:\xampp\php\php.exe artisan storage:link
```

Note : cette etape concerne le **local**. Pour une publication cloud avec stockage objet `s3`, voir [Publication Laravel Cloud](docs/PUBLICATION-LARAVEL-CLOUD.md).

7. Installer les assets front :

```powershell
npm install
npm run build
```

8. Verifier l'environnement local :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\check-nema-erp.ps1
```

9. Lancer l'application :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-nema-erp.ps1
```

Ce script demarre MariaDB XAMPP si necessaire, lance Laravel sur `http://localhost:8000` et ouvre le navigateur.

## Comptes de demonstration

- `admin@nema-erp.test` / `password`
- `dg@nema-erp.test` / `password`
- `manager@nema-erp.test` / `password`
- `ops@nema-erp.test` / `password`

## Sauvegarde rapide

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup-nema-erp.ps1
```

## Verification rapide

```powershell
C:\xampp\php\php.exe artisan test
powershell -ExecutionPolicy Bypass -File .\scripts\check-nema-erp.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\start-nema-erp.ps1 -SkipBrowser
```

## Publication cloud

Pour une vraie mise en ligne publique :

- preparer le depot Git distant
- utiliser [.env.laravel-cloud.example](.env.laravel-cloud.example) comme base
- attacher une base MySQL, un KV store / Redis et un object storage
- preparer le build cloud avec `composer run build:cloud`
- deployer avec `composer run deploy:cloud`

Guide detaille : [Publication Laravel Cloud](docs/PUBLICATION-LARAVEL-CLOUD.md)


## Exploitation phase 1

- page ops : `/operations/sante`
- commande sante : `C:\xampp\php\php.exe artisan nema:ops:health-check --store`
- commande purge outbox : `C:\xampp\php\php.exe artisan nema:ops:outbox-prune --days=30`
- planification : `C:\xampp\php\php.exe artisan schedule:list`
- CI : `.github/workflows/ci.yml`
