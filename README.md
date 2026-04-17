# Nema ERP

Nema ERP est une base Laravel modulaire pour demarrer un ERP destine aux PME maliennes. Cette fondation couvre desormais :

- authentification securisee par session
- roles et permissions maison
- gestion des entreprises
- gestion des agences
- gestion des utilisateurs
- parametres societe et sequences de documents
- workflow d approbation configurable
- moteur d automatisation transverse avec regles, signaux et executions
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
- localisation OHADA / SYSCOHADA visible avec ponts fiscalite, paie et production
- plateforme produit, packaging et endpoint de capacites API
- capital humain avec departements, collaborateurs et conges
- executions de paie avec bulletins et lignes salariales
- projets operationnels
- ordres de production avec nomenclatures et couts matieres
- commerce unifie et canaux digitaux
- point de vente enrichi avec back-office type Odoo
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
- [Plateforme & ecosysteme](docs/PLATEFORME-ECOSYSTEME.md)
- [Exploitation phase 2](docs/EXPLOITATION-PHASE-2.md)

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
      Automation/
    Partners/
    Catalog/
    Inventory/
    Sales/
    Purchases/
    Treasury/
    Expenses/
    Accounting/
    Hr/
    Payroll/
    Projects/
    Manufacturing/
    Commerce/
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
  platform/
  hr/
  payroll/
  projects/
  manufacturing/
  commerce/
  pos/
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

Pour arreter le serveur local :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\stop-nema-erp.ps1
```

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
npm run e2e:smoke
```

Pour lancer un flux critique seul en local :

```powershell
npm run e2e:pos
npm run e2e:portal
npm run e2e:portal:order
npm run e2e:portal:payment
```

Pour lancer ces smokes dans Edge en local :

```powershell
npm run e2e:pos:edge
npm run e2e:portal:edge
npm run e2e:portal:order:edge
npm run e2e:portal:payment:edge
```

## Expansion produit

La vague `Growth Foundation` ouvre maintenant les 4 chantiers strategiques du projet :

- largeur fonctionnelle avec RH, paie, projets, production et commerce unifie
- packaging produit avec scripts d exploitation, checks et documentation
- ecosysteme avec endpoints `GET /api/v1/platform/capabilities` et `GET /api/v1/accounting/localization`
- profondeur metier initiale avec conges RH, bulletins de paie, nomenclatures et localisation OHADA visible
- noyau transverse avec automatisations, cooldown, scheduler et API de regles

## Point de vente enrichi

Le POS couvre maintenant aussi un back-office plus proche d Odoo, avec :

- `Point de Vente > Commandes` pour commandes, brouillons, retours et paiements comptoir
- `Point de Vente > Sessions` pour pilotage des ouvertures, clotures et ecarts
- `Point de Vente > Clients` pour portefeuille comptoir et wallets
- `Point de Vente > Produits` pour catalogue PdV, variantes, combos, categories menu et etiquettes
- `Point de Vente > Tarification` pour listes de prix, fidelite, cartes cadeaux et e-wallet
- `Point de Vente > Analyse` pour ventes, sessions, preparation et temps cibles
- `Point de Vente > Configuration` pour profils de caisse, modes de paiement, imprimantes de preparation, preparation display et modeles de notes
- `Point de Vente > Preparation` pour board cuisine/comptoir, tickets de preparation, `Preparation Display` plein ecran et synchro live multi-poste

## Publication cloud

Pour une vraie mise en ligne publique :

- preparer le depot Git distant
- utiliser [.env.laravel-cloud.example](.env.laravel-cloud.example) comme base
- attacher une base MySQL, un KV store / Redis et un object storage
- si tu veux la synchro quasi instantanee des `Preparation Display`, attacher aussi un service WebSocket compatible Reverb / Pusher et renseigner `BROADCAST_CONNECTION` + variables associees
- preparer le build cloud avec `composer run build:cloud`
- deployer avec `composer run deploy:cloud`

Guide detaille : [Publication Laravel Cloud](docs/PUBLICATION-LARAVEL-CLOUD.md)


## Exploitation phase 1

- page ops : `/operations/sante`
- commande sante : `C:\xampp\php\php.exe artisan nema:ops:health-check --store`
- commande purge outbox : `C:\xampp\php\php.exe artisan nema:ops:outbox-prune --days=30`
- planification : `C:\xampp\php\php.exe artisan schedule:list`
- CI : `.github/workflows/ci.yml`

## Exploitation phase 2

- Redis/Horizon : `QUEUE_CONNECTION=redis` + worker dedie (`php artisan queue:work --tries=3 --timeout=120 --max-time=3600`)
- supervision centralisee : `php artisan nema:ops:monitor-app --json` + ingestion externe
- alerting externe : `php artisan nema:ops:alert-dispatch`
- staging/prod automatise : `.github/workflows/promote-staging-prod.yml`
- sauvegardes hors machine : `php artisan nema:ops:backup-offsite-sync` + `php artisan nema:ops:backup-offsite-verify`
- puissance noyau : `php artisan nema:core:pulse --json --store` (SLA + tendances)
- execution 5 priorites : `php artisan nema:ops:execute-priorities --apply --json`
